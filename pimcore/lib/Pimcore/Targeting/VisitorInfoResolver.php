<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Targeting;

use Doctrine\DBAL\Connection;
use Pimcore\Event\Targeting\TargetingEvent;
use Pimcore\Event\Targeting\TargetingRuleEvent;
use Pimcore\Event\TargetingEvents;
use Pimcore\Model\Tool\Targeting\Rule;
use Pimcore\Targeting\ActionHandler\ActionHandlerInterface;
use Pimcore\Targeting\ActionHandler\DelegatingActionHandler;
use Pimcore\Targeting\Model\VisitorInfo;
use Pimcore\Targeting\Storage\TargetingStorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class VisitorInfoResolver
{
    const ATTRIBUTE_VISITOR_INFO = '_visitor_info';

    /**
     * @var TargetingStorageInterface
     */
    private $targetingStorage;

    /**
     * @var VisitorInfoStorageInterface
     */
    private $visitorInfoStorage;

    /**
     * @var ConditionMatcherInterface
     */
    private $conditionMatcher;

    /**
     * @var DelegatingActionHandler|ActionHandlerInterface
     */
    private $actionHandler;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Rule[]
     */
    private $targetingRules;

    public function __construct(
        TargetingStorageInterface $targetingStorage,
        VisitorInfoStorageInterface $visitorInfoStorage,
        ConditionMatcherInterface $conditionMatcher,
        ActionHandlerInterface $actionHandler,
        Connection $db,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->targetingStorage   = $targetingStorage;
        $this->visitorInfoStorage = $visitorInfoStorage;
        $this->conditionMatcher   = $conditionMatcher;
        $this->actionHandler      = $actionHandler;
        $this->eventDispatcher    = $eventDispatcher;
        $this->db                 = $db;
    }

    public function resolve(Request $request): VisitorInfo
    {
        if ($this->visitorInfoStorage->hasVisitorInfo()) {
            return $this->visitorInfoStorage->getVisitorInfo();
        }

        $visitorInfo = VisitorInfo::fromRequest($request);

        if (!$this->isTargetingConfigured()) {
            return $visitorInfo;
        }

        $this->eventDispatcher->dispatch(
            TargetingEvents::PRE_RESOLVE,
            new TargetingEvent($visitorInfo)
        );

        $this->matchTargetingRuleConditions($visitorInfo);

        $this->eventDispatcher->dispatch(
            TargetingEvents::POST_RESOLVE,
            new TargetingEvent($visitorInfo)
        );

        $this->visitorInfoStorage->setVisitorInfo($visitorInfo);

        return $visitorInfo;
    }

    public function isTargetingConfigured(): bool
    {
        $configuredRules = $this->db->fetchColumn(
            'SELECT id FROM targeting_personas UNION SELECT id FROM targeting_rules LIMIT 1'
        );

        return $configuredRules && (int)$configuredRules > 0;
    }

    private function matchTargetingRuleConditions(VisitorInfo $visitorInfo)
    {
        $rules = $this->getTargetingRules();

        foreach ($rules as $rule) {
            if (Rule::SCOPE_SESSION === $rule->getScope()) {
                if (!$this->ruleWasMatchedInSession($visitorInfo, $rule)) {
                    continue;
                }
            } elseif (Rule::SCOPE_USER === $rule->getScope()) {
                if (!empty($visitorInfo->getVisitorId())) {
                    continue;
                }
            }

            $this->matchTargetingRuleCondition($visitorInfo, $rule);
        }
    }

    private function matchTargetingRuleCondition(VisitorInfo $visitorInfo, Rule $rule)
    {
        $scopeWithVariables = Rule::SCOPE_SESSION_WITH_VARIABLES === $rule->getScope();

        $match = $this->conditionMatcher->match(
            $visitorInfo,
            $rule->getConditions(),
            $scopeWithVariables
        );

        if (!$match) {
            return;
        }

        if ($scopeWithVariables) {
            $collectedVariables = $this->conditionMatcher->getCollectedVariables();

            // match only once with the same variables
            if ($this->ruleWasMatchedInSessionWithVariables($visitorInfo, $rule, $collectedVariables)) {
                return;
            }
        }

        // record the rule as matched for the current session
        if (Rule::SCOPE_SESSION === $rule->getScope()) {
            $this->addMatchedSessionRule($visitorInfo, $rule);
        }

        // store info about matched rule
        $visitorInfo->addMatchingTargetingRule($rule);

        $this->eventDispatcher->dispatch(
            TargetingEvents::PRE_RULE_ACTIONS,
            new TargetingRuleEvent($visitorInfo, $rule)
        );

        // execute rule actions
        $this->handleTargetingRuleActions($visitorInfo, $rule);

        $this->eventDispatcher->dispatch(
            TargetingEvents::POST_RULE_ACTIONS,
            new TargetingRuleEvent($visitorInfo, $rule)
        );
    }

    private function handleTargetingRuleActions(VisitorInfo $visitorInfo, Rule $rule)
    {
        $actions = $rule->getActions();
        if (!$actions || !is_array($actions)) {
            return;
        }

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $this->actionHandler->apply($visitorInfo, $action, $rule);
        }
    }

    /**
     * @return Rule[]
     */
    private function getTargetingRules(): array
    {
        if (null !== $this->targetingRules) {
            return $this->targetingRules;
        }

        /** @var Rule\Listing|Rule\Listing\Dao $list */
        $list = new Rule\Listing();
        $list->setCondition('active = 1');
        $list->setOrderKey('prio');
        $list->setOrder('ASC');

        $this->targetingRules = $list->load();

        return $this->targetingRules;
    }

    private function ruleWasMatchedInSession(VisitorInfo $visitorInfo, Rule $rule): bool
    {
        $matchedRules = $this->targetingStorage->get($visitorInfo, 'matched_session_rules', []);

        if (in_array($rule->getId(), $matchedRules)) {
            return false;
        }

        return true;
    }

    private function addMatchedSessionRule(VisitorInfo $visitorInfo, Rule $rule)
    {
        $matchedRules   = $this->targetingStorage->get($visitorInfo, 'matched_session_rules', []);
        $matchedRules[] = $rule->getId();

        $this->targetingStorage->set($visitorInfo, 'matched_session_rules', $matchedRules);
    }

    private function ruleWasMatchedInSessionWithVariables(VisitorInfo $visitorInfo, Rule $rule, array $variables): bool
    {
        $hash = sha1(serialize($variables));

        $storedVariables = $this->targetingStorage->get($visitorInfo, 'rule_condition_variables', []);

        // hash was already matched
        if (isset($storedVariables[$rule->getId()]) && $storedVariables[$rule->getId()] === $hash) {
            return true;
        }

        // store hash to storage
        $storedVariables[$rule->getId()] = $hash;
        $this->targetingStorage->set($visitorInfo, 'rule_condition_variables', $storedVariables);

        return false;
    }
}
