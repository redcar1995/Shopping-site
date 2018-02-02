<?php
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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Payment;

use Pimcore\Bundle\EcommerceFrameworkBundle\Model\Currency;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\IStatus;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Status;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\IPrice;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\Price;
use Pimcore\Bundle\EcommerceFrameworkBundle\Type\Decimal;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QPay implements IPayment
{
    // supported hashing algorithms
    const HASH_ALGO_MD5 = 'md5';
    const HASH_ALGO_HMAC_SHA512 = 'hmac_sha512';

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var string
     */
    protected $customer;

    /**
     * @var string
     */
    protected $toolkitPassword;

    /**
     * @var string
     */
    protected $paymenttype = 'SELECT';

    /**
     * Keep old implementation for backwards compatibility
     *
     * @var string
     */
    protected $hashAlgorithm = self::HASH_ALGO_MD5;

    /**
     * @var string[]
     */
    protected $authorizedData;

    /**
     * Whitelist of optional properties allowed for payment init
     *
     * @var array
     */
    protected $optionalPaymentProperties = [
        'imageURL',
        'confirmURL',
        'confirmMail',
        'displayText',
        'shopId' // value=mobile for mobile checkout page
    ];

    public function __construct(array $options, FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;

        $this->processOptions(
            $this->configureOptions(new OptionsResolver())->resolve($options)
        );
    }

    protected function processOptions(array $options)
    {
        $this->customer = $options['customer'];
        $this->secret   = $options['secret'];

        if (isset($options['toolkit_password'])) {
            $this->toolkitPassword = $options['toolkit_password'];
        }

        if (isset($options['payment_type'])) {
            $this->paymenttype = $options['payment_type'];
        }

        if (isset($options['hash_algorithm'])) {
            $this->hashAlgorithm = $options['hash_algorithm'];
        }

        if (isset($options['optional_payment_properties'])) {
            $this->optionalPaymentProperties = array_unique(array_merge(
                $this->optionalPaymentProperties,
                $options['optional_payment_properties']
            ));
        }
    }

    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        $resolver->setRequired([
            'customer',
            'secret'
        ]);

        $resolver
            ->setDefined('toolkit_password')
            ->setAllowedTypes('toolkit_password', ['string']);

        $resolver
            ->setDefined('payment_type')
            ->setAllowedTypes('payment_type', ['string']);

        $resolver
            ->setDefined('hash_algorithm')
            ->setAllowedValues('hash_algorithm', [
                self::HASH_ALGO_MD5,
                self::HASH_ALGO_HMAC_SHA512
            ]);

        $resolver
            ->setDefined('optional_payment_properties')
            ->setAllowedTypes('optional_payment_properties', 'array');

        $notEmptyValidator = function ($value) {
            return !empty($value);
        };

        foreach ($resolver->getRequiredOptions() as $requiredProperty) {
            $resolver->setAllowedValues($requiredProperty, $notEmptyValidator);
        }

        return $resolver;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Qpay';
    }

    /**
     * @param array $formAttributes
     * @param IPrice $price
     * @param array $config
     *
     * @return array
     */
    protected function extendFormAttributes(array $formAttributes, IPrice $price, array $config): array
    {
        return $formAttributes;
    }

    /**
     * Start payment
     *
     * @param IPrice $price
     * @param array $config
     *
     * @return FormBuilderInterface
     *
     * @throws \Exception
     */
    public function initPayment(IPrice $price, array $config)
    {
        // check params
        $required = $this->getRequiredRequestFields();

        $check = array_intersect_key($config, $required);
        if (count($required) != count($check)) {
            throw new \Exception(sprintf('required fields are missing! required: %s', implode(', ', array_keys(array_diff_key($required, $check)))));
        }

        // collect payment data
        $paymentData['secret'] = $this->secret;
        $paymentData['customerId'] = $this->customer;
        $paymentData['amount'] = round($price->getAmount()->asNumeric(), 2);
        $paymentData['currency'] = $price->getCurrency()->getShortName();
        $paymentData['duplicateRequestCheck'] = 'yes';

        // can be overridden by adding paymentType to optional properties and passing its value in config
        $paymentData['paymentType'] = $this->paymenttype;

        foreach ($required as $property => $null) {
            $paymentData[$property] = $config[$property];
        }

        // handle optional properties
        foreach ($this->optionalPaymentProperties as $optionalProperty) {
            if (array_key_exists($optionalProperty, $config)) {
                $paymentData[$optionalProperty] = $config[$optionalProperty];
            }
        }

        // set fingerprint order
        $paymentData['requestFingerprintOrder'] = ''; // make sure the key is in the order array
        $paymentData['requestFingerprintOrder'] = implode(',', array_keys($paymentData));

        // compute fingerprint
        $fingerprint = $this->computeFingerprint(array_values($paymentData));

        // create form
        $formData = [];
        $formAttributes = [];

        $formAttributes['id'] = 'paymentForm';

        $formAttributes = $this->extendFormAttributes(['id' => 'paymentForm'], $price, $config);

        //form name needs to be null in order to make sure the element names are correct - and not FORMNAME[ELEMENTNAME]
        $form = $this->formFactory->createNamedBuilder(null, FormType::class, [], [
            'attr' => $formAttributes
        ]);

        $form->setAction('https://www.qenta.com/qpay/init.php');
        $form->setMethod('post');
        $form->setAttribute('data-currency', 'EUR');

        // omit these keys from the form
        $blacklistedFormKeys = ['secret'];
        foreach ($paymentData as $property => $value) {
            if (in_array($property, $blacklistedFormKeys)) {
                continue;
            }

            $form->add($property, HiddenType::class);
            $formData[$property] = $value;
        }

        // add fingerprint to request
        $form->add('requestFingerprint', HiddenType::class);
        $formData['requestFingerprint'] = $fingerprint;

        // add submit button
        $form->add('submitbutton', SubmitType::class, ['attr' => ['class' => 'btn']]);

        $form->setData($formData);

        return $form;
    }

    /**
     * Handles response of payment provider and creates payment status object
     *
     * @param mixed $response
     *
     * @return IStatus
     *
     * @throws \Exception
     */
    public function handleResponse($response)
    {
        //unsetting response document because it is not needed (and spams up log files)
        unset($response['document']);

        // check required fields
        $required = [
            'orderIdent' => null
        ];

        $authorizedData = [
            'orderNumber' => null,
            'language'    => null,
            'amount'      => null,
            'currency'    => null
        ];

        // check fields
        $check = array_intersect_key($response, $required);
        if (count($required) != count($check)) {
            throw new \Exception(sprintf('required fields are missing! required: %s', implode(', ', array_keys(array_diff_key($required, $check)))));
        }

        // build fingerprint params
        $fingerprintParams = [];
        $fingerprintFields = explode(',', $response['responseFingerprintOrder']);
        foreach ($fingerprintFields as $field) {
            $fingerprintParams[] = $field === 'secret' ? $this->secret : $response[$field];
        }

        // compute and check fingerprint
        $fingerprint = $this->computeFingerprint($fingerprintParams);
        if ($response['paymentState'] !== 'FAILURE' && $fingerprint != $response['responseFingerprint']) {
            // fingerprint is wrong, ignore this response
            return new Status(
                $response['orderIdent'],
                $response['orderNumber'],
                $response['avsResponseMessage'] ?: $response['message'] ?: 'fingerprint error',
                IStatus::STATUS_CANCELLED
            );
        }

        // handle
        $authorizedData = array_intersect_key($response, $authorizedData);
        $this->setAuthorizedData($authorizedData);

        // restore price object for payment status
        $decimal = Decimal::zero();
        if ($authorizedData['amount']) {
            $decimal = Decimal::create($authorizedData['amount']);
        }
        $price = new Price($decimal, new Currency($authorizedData['currency']));

        return new Status(
            $response['orderIdent'],
            $response['orderNumber'],
            $response['avsResponseMessage'] ?: $response['message'],
            $response['orderNumber'] !== null && $response['paymentState'] == 'SUCCESS'
                ? IStatus::STATUS_AUTHORIZED
                : IStatus::STATUS_CANCELLED,
            [
                'qpay_amount'       => (string)$price,
                'qpay_paymentType'  => $response['paymentType'],
                'qpay_paymentState' => $response['paymentState'],
                'qpay_response'     => $response
            ]
        );
    }

    /**
     * @return array
     */
    protected function getRequiredRequestFields(): array
    {
        return [
            'successURL'       => null,
            'cancelURL'        => null,
            'failureURL'       => null,
            'serviceURL'       => null,
            'orderDescription' => null,
            'orderIdent'       => null,
            'language'         => null,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAuthorizedData()
    {
        return $this->authorizedData;
    }

    /**
     * @inheritdoc
     */
    public function setAuthorizedData(array $authorizedData)
    {
        $this->authorizedData = $authorizedData;
    }

    /**
     * Executes payment
     *
     *  if price is given, recurPayment command is executed
     *  if no price is given, amount from authorized Data is used and deposit command is executed
     *
     * @param IPrice $price
     * @param string $reference
     *
     * @return IStatus
     *
     * @throws \Exception
     */
    public function executeDebit(IPrice $price = null, $reference = null)
    {
        // https://integration.wirecard.at/doku.php/wcp:toolkit_light:start
        // https://integration.wirecard.at/doku.php/wcs:backend_operations?s[]=deposit
        // https://integration.wirecard.at/doku.php/backend:deposit

        if ($price) {
            // recurPayment

            $request = [
                'customerId' => $this->customer,
                'toolkitPassword' => $this->toolkitPassword,
                'command' => 'recurPayment',
                'language' => $this->authorizedData['language'],
                'requestFingerprint' => '',
                'orderDescription' => $reference,
                'sourceOrderNumber' => $this->authorizedData['orderNumber'],
                'amount' => $price->getAmount()->asNumeric(),
                'currency' => $price->getCurrency()->getShortName()
            ];

            // add fingerprint
            $request['requestFingerprint'] = $this->computeFingerprint([
                $request['customerId'],
                $request['toolkitPassword'],
                $this->secret,
                $request['command'],
                $request['language'],
                $request['sourceOrderNumber'],
                $request['orderDescription'],
                $request['amount'],
                $request['currency']
            ]);
        } else {
            // default clearing auth
            $price = new Price(Decimal::create($this->authorizedData['amount']), new Currency($this->authorizedData['currency']));

            $request = [
                'customerId' => $this->customer,
                'toolkitPassword' => $this->toolkitPassword,
                'command' => 'deposit',
                'language' => $this->authorizedData['language'],
                'requestFingerprint' => '',
                'orderNumber' => $this->authorizedData['orderNumber'],
                'amount' => $price->getAmount()->asNumeric(),
                'currency' => $price->getCurrency()->getShortName()
            ];

            // add fingerprint
            $request['requestFingerprint'] = $this->computeFingerprint([
                $request['customerId'],
                $request['toolkitPassword'],
                $this->secret,
                $request['command'],
                $request['language'],
                $request['orderNumber'],
                $request['amount'],
                $request['currency']
            ]);
        }

        // execute request
        $response = $this->serverToServerRequest('https://checkout.wirecard.com/page/toolkit.php', $request);

        // check response
        if ($response['status'] === '0') {
            // Operation successfully done.

            return new Status(
                $reference,
                $response['paymentNumber'] ?: $response['orderNumber'],
                '',
                IStatus::STATUS_CLEARED,
                [
                    'qpay_amount'   => (string)$price,
                    'qpay_command'  => $request['command'],
                    'qpay_response' => $response
                ]
            );
        } elseif ($response['errors']) {
            // https://integration.wirecard.at/doku.php/backend:response_parameters

            $error = [];
            for ($e = 1; $e <= $response['errors']; $e++) {
                $error[] = $response['error_' . $e . '_error_message'];
            }

            return new Status(
                $reference,
                $response['paymentNumber'] ?: $response['orderNumber'],
                implode("\n", $error),
                IStatus::STATUS_CANCELLED,
                [
                    'qpay_amount'   => (string)$price,
                    'qpay_command'  => $request['command'],
                    'qpay_response' => $response
                ]
            );
        } else {
            throw new \Exception(print_r($response, true));
        }
    }

    /**
     * Executes credit
     *
     * @param IPrice $price
     * @param string $reference
     * @param $transactionId
     *
     * @return IStatus
     *
     * @throws \Exception
     */
    public function executeCredit(IPrice $price, $reference, $transactionId)
    {
        // init request
        $request = [
            'customerId' => $this->customer,
            'toolkitPassword' => $this->toolkitPassword,
            'command' => 'refund',
            'language' => $this->authorizedData['language'],
            'requestFingerprint' => '',
            'orderNumber' => $reference,
            'amount' => $price->getAmount()->asNumeric(),
            'currency' => $price->getCurrency()->getShortName(),
            'merchantReference' => $transactionId
        ];

        // add fingerprint
        $request['requestFingerprint'] = $this->computeFingerprint([
            $request['customerId'],
            $request['toolkitPassword'],
            $this->secret,
            $request['command'],
            $request['language'],
            $request['orderNumber'],
            $request['amount'],
            $request['currency'],
            $request['merchantReference']
        ]);

        // execute request
        $response = $this->serverToServerRequest('/page/toolkit.php', $request);

        // check response
        if ($response['status'] === '0') {
            // Operation successfully done.

            return new Status(
                $transactionId,
                $reference,
                'executeCredit',
                IStatus::STATUS_CLEARED,
                [
                    'qpay_amount'   => (string)$price,
                    'qpay_command'  => $request['command'],
                    'qpay_response' => $response
                ]
            );
        } elseif ($response['errorCode']) {
            // https://integration.wirecard.at/doku.php/backend:response_parameters

            return new Status(
                $transactionId,
                $reference,
                $response['message'],
                IStatus::STATUS_CANCELLED,
                [
                    'qpay_amount'   => (string)$price,
                    'qpay_command'  => $request['command'],
                    'qpay_response' => $response
                ]
            );
        } else {
            throw new \Exception(print_r($response, true));
        }
    }

    /**
     * Compute fingerprint for array of input parameters depending on configured algorithm
     *
     * @param array $params
     *
     * @return string
     */
    protected function computeFingerprint(array $params)
    {
        $data   = implode('', $params);
        $result = null;

        switch ($this->hashAlgorithm) {
            case static::HASH_ALGO_MD5:
                return $this->computeMd5Fingerprint($data);

            case static::HASH_ALGO_HMAC_SHA512:
                return $this->computeHmacSha512Fingerprint($data);

            default:
                throw new \LogicException(sprintf('Hash algorithm "%s" is not supported', $this->hashAlgorithm));
        }
    }

    /**
     * Compute MD5 fingerprint
     *
     * @param $data
     *
     * @return string
     */
    protected function computeMd5Fingerprint($data)
    {
        return md5($data);
    }

    /**
     * Calculate HMAC_SHA512 fingerprint
     *
     * @param $data
     *
     * @return string
     */
    protected function computeHmacSha512Fingerprint($data)
    {
        return hash_hmac('sha512', $data, $this->secret);
    }

    /**
     * @param $url
     * @param $params
     *
     * @return string[]
     */
    protected function serverToServerRequest($url, $params)
    {
        $postFields = '';
        foreach ($params as $key => $value) {
            $postFields .= $key . '=' . $value . '&';
        }

        $postFields = substr($postFields, 0, strlen($postFields) - 1);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_PORT, 443);
        curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        $response = curl_exec($curl);
        curl_close($curl);

        $r = [];
        parse_str($response, $r);

        return $r;
    }

    public function setPaymentType($paymentType)
    {
        $this->paymenttype = $paymentType;
    }

    public function getPaymentType()
    {
        return $this->paymenttype;
    }
}
