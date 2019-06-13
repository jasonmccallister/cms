<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\App;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use craft\helpers\MailerHelper;
use craft\mail\Message;
use craft\mail\transportadapters\BaseTransportAdapter;
use craft\mail\transportadapters\Gmail;
use craft\mail\transportadapters\Sendmail;
use craft\mail\transportadapters\Smtp;
use craft\models\MailSettings;
use yii\base\InvalidArgumentException;
use yii\console\ExitCode;
use craft\elements\User as UserElement;

/**
 * Various support resources for testing Craft.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.2
 */
class TestsController extends Controller
{
    // Public functions
    // =========================================================================

    /**
     * @return int
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionTestEmailSettings()
    {
        $recieverEmail = $this->prompt('Which email address must we send this test email to?');

        $mailParams = [
            'user' => new UserElement([
                'email' => $recieverEmail,
                'username' => $recieverEmail
            ])
        ];

        $settingsModel = App::mailSettings();

        // Default settings?
        if ($this->confirm('Do you want to test Crafts default email settings?')) {
            $adapter = MailerHelper::createTransportAdapter(
                $settingsModel->transportType,
                $settingsModel->transportSettings
            );

            $mailParams['settings'] = $this->_renderMailSettingsString(
                App::mailSettings(),
                $adapter
            );

            $message = Craft::$app->getMailer()
                ->composeFromKey('test_email', $mailParams)
                ->setTo($recieverEmail);

            return $this->_testEmailSending($message);
        }

        // Otherwise we let the user decide....
        $transportAdapters = [
            Smtp::class,
            Gmail::class,
            Sendmail::class,
            'Other'
        ];
        $selectedOption = null;

        foreach ($transportAdapters as $transportAdapter) {
            if ($this->confirm("Do you want to user {$transportAdapter}?".PHP_EOL)) {
                $selectedOption = $transportAdapter;
                break;
            }
        }

        if ($selectedOption === 'Other') {
            $selectedOption = $this->prompt("Which transport type do you want to use?");
        }

        /* @var BaseTransportAdapter $transport */
        $transport = new $selectedOption();

        if (!$transport instanceof BaseTransportAdapter) {
            $this->stderr("$selectedOption is not an instance of " . BaseTransportAdapter::class . "");
            return ExitCode::OK;
        }

        // What do they want to use?
        foreach ($transport->settingsAttributes() as $property) {
            $transport->$property = $this->prompt("What must $property be set to?");
        }

        // Save the new stuff to the settings
        $settingsModel->transportType = $transport::displayName();
        $settingsModel->transportSettings = $transport->getSettings();

        // Too easy?
        if (!$transport->validate()) {
            $this->stderr('Your email settings are invalid.');
            return ExitCode::OK;
        }

        // Setup the mailer.
        $mailer = Craft::$app->getMailer();
        $mailer->transport = $transport->defineTransport();

        // For the template
        $mailParams['settings'] = $this->_renderMailSettingsString($settingsModel, $transport);

        $message = $mailer
            ->composeFromKey('test_email', $mailParams)
            ->setTo($recieverEmail);

        // FOR... SPARTAAA!
        return $this->_testEmailSending($message);
    }

    /**
     * Sets up a test suite for the current project.
     *
     * @param string|null $dst The folder that the test suite should be generated in.
     * Defaults to the current working directory.
     * @return int
     */
    public function actionSetup(string $dst = null): int
    {
        if ($dst === null) {
            $dst = getcwd();
        }

        $src = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'internal' . DIRECTORY_SEPARATOR . 'example-test-suite';

        // Figure out the plan and check for conflicts
        $plan = [];
        $conflicts = [];

        $handle = opendir($src);
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open directory: $src");
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;
            $humanTo = $to . (is_dir($from) ? DIRECTORY_SEPARATOR : '');
            $plan[] = $humanTo;
            if (file_exists($to)) {
                $conflicts[] = $humanTo;
            }
        }
        closedir($handle);

        // Warn about conflicts
        if (!empty($conflicts)) {
            $this->stdout('The following files/folders will be overwritten:' . PHP_EOL . PHP_EOL, Console::FG_YELLOW);
            foreach ($conflicts as $file) {
                $this->stdout("- {$file}" . PHP_EOL, Console::FG_YELLOW);
            }
            $this->stdout(PHP_EOL);
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->stdout('Aborting.' . PHP_EOL);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout(PHP_EOL);
        }

        // Confirm
        $this->stdout('The following files/folders will be created:' . PHP_EOL . PHP_EOL);
        foreach ($plan as $file) {
            $this->stdout("- {$file}" . PHP_EOL);
        }
        $this->stdout(PHP_EOL);
        if (!$this->confirm('Continue?', true)) {
            $this->stdout('Aborting.' . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(PHP_EOL . 'Generating the test suite ... ');
        try {
            FileHelper::copyDirectory($src, $dst);
        } catch (\Throwable $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stdout('error: ' . $e->getMessage() . PHP_EOL . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout('done.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Dont use this method - it wont actually execute anything.
     * It is just used internally to test Craft-based console controller testing.
     *
     * @return int
     * @internal
     */
    public function actionTest(): int
    {
        $this->stdout('22');
        $this->stderr('123321123');
        $val = $this->select('Select', ['2', '22']);

        if ($val !== '2') {
            throw new InvalidArgumentException('FAIL');
        }

        $confirm = $this->confirm('asd', true);
        if ($confirm !== true) {
            throw new InvalidArgumentException('FAIL');
        }

        $prompts = $this->prompt('A prompt', ['2', '22']);
        if ($prompts !== 'hi') {
            throw new InvalidArgumentException('FAIL');
        }

        $this->outputCommand('An output command');

        return ExitCode::OK;
    }

    // Protected functions
    // =========================================================================

    /**
     * @param MailSettings $settings
     * @param $adapter
     * @return string
     */
    protected function _renderMailSettingsString(MailSettings $settings, $adapter) : string
    {
        // Compose the settings list as HTML
        $settingsList = '';

        foreach (['fromEmail', 'fromName', 'template'] as $name) {
            if (!empty($settings->$name)) {
                $settingsList .= '- **' . $settings->getAttributeLabel($name) . ':** ' . $settings->$name . "\n";
            }
        }

        $settingsList .= '- **' . 'Transport Type' . ':** ' . $adapter::displayName() . "\n";

        $security = Craft::$app->getSecurity();

        foreach ($adapter->settingsAttributes() as $name) {
            if (!empty($adapter->$name)) {
                $label = $adapter->getAttributeLabel($name);
                $value = $security->redactIfSensitive($name, $adapter->$name);
                $settingsList .= "- **{$label}:** {$value}\n";
            }
        }

        return $settingsList;
    }

    /**
     * @param Message $message
     * @return int
     */
    protected function _testEmailSending(Message $message) : int
    {
        if ($message->send()) {
            $this->stdout('Email sent successfully! Check your inbox.'.PHP_EOL.PHP_EOL);
        } else {
            $this->stderr('There was an error testing your email settings.'.PHP_EOL.PHP_EOL);
        }

        return ExitCode::OK;
    }
}
