# Email Framework

## General Information
The Pimcore Email Framework provides an easy way to send/create emails with Pimcore.
  
For this you have several components:
* Document\\Email
* [Pimcore\Mail](./01_Pimcore_Mail.md)

Pimcore provides a `Pimcore\Mail` Class which extends the `\Symfony\Component\Mime\Email` Class. 
If email settings are configured in your `config/config.yaml` then on initializing 
`Pimcore\Mail` object, these settings applied automatically

It is recommended to configure email settings in `config/config.yaml` file:
```yaml
pimcore:
    email:
        sender:
            name: 'Pimcore Demo'
            email: demo@pimcore.com
        return:
            name: ''
            email: ''
```
and debug email addresses should be configured in Admin *Settings* > *System* > *Debug* > *Debug Email Addresses*.

If the Debug Mode is enabled, all emails will be sent to the
Debug Email recipients defined in *Settings* > *System* > *Debug* > *Debug Email Addresses*.
Additionally the debug information (to whom the email would have been sent) is appended to the email
and the Subject contains the prefix "Debug email:".

This is done by extending Symfony Mailer, with injected service `RedirectingPlugin`, which calls beforeSendPerformed before mail is sent and sendPerformed immediately after email is sent.

Emails are sent via transport and `\Pimcore\Mailer` requires transports: `main` for sending emails and  `pimcore_newsletter` for sending newsletters(if newsletter specific settings are used), which needs to be configured in your config.yaml e.g.,
```yaml
framework:
    mailer:
        transports:
            main: smtp://user:pass@smtp.example.com:port
            pimcore_newsletter: smtp://user:pass@smtp.example.com:port
```
Please refer to the [Transport Setup](https://symfony.com/doc/5.2/mailer.html#transport-setup) for further details on how this can be set up.


Pimcore provides a `Document Email` type where you can define the recipients ... (more information 
[here](../../03_Documents/README.md)) and Twig variables. 

To send a email you just create a `Email Document` in the Pimcore Backend UI, define the subject, 
recipients, add Dynamic Placeholders... and pass this document to the `Pimcore\Mail` object. All 
nasty stuff (creating valid URLs, embedding CSS, compile Less files, rendering the document..) is 
automatically handled by the `Pimcore\Mail` object.

In the `Settings` section of the `Email Document` you can use `Full Username <user@domain.fr>` or `Full Username (user@domain.fr)` to set full username.

## Usage Example
Lets assume that we have created a `Email Document` in the Pimcore Backen UI (`/email/myemaildocument`) 
which looks like this:

![Pimcore Mail](../../img/pimcore-mail.png)

To send this document as email we just have to write the following code-snippet in our controller 
action:

```php
//dynamic parameters
$params = array('firstName' => 'Pim',
                'lastName' => 'Core',
                'product' => 73613);
 
//sending the email
$mail = new \Pimcore\Mail();
$mail->to('example@pimcore.org');
$mail->setDocument('/email/myemaildocument');
$mail->setParams($params);
$mail->send();
```


#### Sending a Plain Text Email:
```php
$mail = new \Pimcore\Mail();
$mail->to('example@pimcore.org');
$mail->text("This is just plain text");
$mail->send();
```

#### Sending a Rich Text (HTML) Email: 
```php
$mail = new \Pimcore\Mail();
$mail->to('example@pimcore.org');
$mail->bcc("bcc@pimcore.org");
$mail->html("<b>some</b> rich text");
$mail->send();
```
