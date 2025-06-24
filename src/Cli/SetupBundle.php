<?php
namespace Horde\Horde\Cli;
use Horde_Argv_Parser;
use Horde_Argv_Values;
use Horde_Core_Bundle;
use Horde_Variables;
use Horde_Core_Cli;
use Horde_Registry;
use Horde_Config;
use Horde_Config_Form;
use Horde\Core\Config\State as ExistingConfig;

class SetupBundle extends Horde_Core_Bundle
{
    /**
     * Constructor.
     */
    const NAME = 'horde';
    const FULLNAME = 'Horde';
    protected ExistingConfig $existingConfig;
    protected Horde_Argv_Parser $parser;
    protected Horde_Argv_Values $cliValues;

    public function __construct(Horde_Core_Cli $cli, $pearconf = null)
    {
        $description = <<<TEXT
Horde Setup CLI Tool

Starts a conf.php from conf.php.dist

Adds any config values from CLI options. Names follow these patterns:
- Option name will be prefixed with `--`
- Multi-level options will be separated by a single hyphen, e.g. `--auth-driver` for \$conf['auth']['driver'].
- Underscores in option names will be replaced with single dashes
- Boolean switches will have two options: `--option-true` and `--option-false`. They do not allow values
- String and text options will accept a value, e.g. `--option=value`
- Enum options will be presented as a choice list, e.g. `--option=value1` or `--option=value2`.

Prompts for SQL database configuration and writes it to the configuration file unless the configuration already exists.
Tests SQL configuration
Applies horde schema upgrades.

Checks if at least one administrator user exists, prompts for a new user if none exists.

Use --help to see available options.

TEXT;
       $this->_cli = $cli;
        $this->_pearconf = $pearconf;
        $this->parser = new Horde_Argv_Parser([
            'usage' => 'horde-setup [options]',
            'description' => $description,
        ]);
    }

    /**
     * Initialize the setup bundle. Place a default horde configuration if missing.
     */
    public function init(): void
    {
        $configFileDir = HORDE_CONFIG_BASE . '/horde';
        $configFilePath = $configFileDir . '/conf.php';

        // Check if conf.php is writeable.
        if ((file_exists($configFilePath) &&
             !is_writable($configFilePath)) ||
            !is_writable($configFileDir)) {
            $this->_cli->message(Horde_Util::realPath($configFilePath) . ' is not writable.', 'cli.error');
        }

        // We need a valid conf.php to instantiate the registry.
        if (!file_exists($configFilePath)) {
            copy(HORDE_BASE . '/config/conf.php.dist', $configFilePath);
        }

        // Initialization
        $umask = umask();
        Horde_Registry::appInit('horde', ['nocompress' => true, 'authentication' => 'none']);
        $this->_config = new Horde_Config();
        $this->existingConfig = $GLOBALS['injector']->getInstance(ExistingConfig::class);
        umask($umask);
        $this->initParser();
        $this->parser->parseArgs();
        if ($this->parser->values->usage) {
            $this->_cli->writeln($this->parser->getUsage());
            $this->_cli->writeln($this->parser->getDescription());
            exit(0);
        }
        $this->cliValues = $this->parser->values;
        // Apply CLI values to the existing configuration.
        $vars = new Horde_Variables();
        $form = new Horde_Config_Form($vars, 'horde', true);
        // Cli trumps existing configuration.
        foreach ($this->filteredCliValues() as $key => $value) {
            // Set the value in the variables object.
            $vars->set($key, $value);
        }
        $this->writeConfig($vars);
    }

    public function initParser(): void
    {
            $this->parser->addOption('', '--usage', array(
                'action' => 'store_true',
                'default' => null,
                'dest' => 'usage',
                'help' => $optionHelp
            ));
        $vars = new Horde_Variables();
        $form = new Horde_Config_Form($vars, 'horde', true);
        foreach ($form->getVariables() as $configField) {
            // Setup CLI options from config Form
            // TODO: Refine handling of boolean fields
            $optionHelp = $output = preg_replace('/\s+/', ' ', $configField->description);
            $baseOptionName = '--' . str_replace(['__', '_'], '-', $configField->varName);
            if ($configField->type->getTypeName() == 'boolean') {
                $this->parser->addOption('', $baseOptionName  . '-true', array(
                    'action' => 'store_true',
                    'default' => null,
                    'dest' => $configField->varName,
                    'help' => $optionHelp
                ));
                $this->parser->addOption('', $baseOptionName  . '-false', array(
                    'action' => 'store_false',
                    'default' => null,
                    'dest' => $configField->varName,
                    'help' => $optionHelp
                ));
                continue;
            } elseif(in_array($configField->type->getTypeName(), ['int'])) {
                $this->parser->addOption('', $baseOptionName, array(
                    'action' => 'store',
                    'type' => 'int',
                    'default' => null,
                    'dest' => $configField->varName,
                    'help' => $optionHelp
                ));
            } elseif(in_array($configField->type->getTypeName(), ['description', 'header'])) {
                    // Skip description and header types as they are not needed for CLI options.
            } elseif(in_array($configField->type->getTypeName(), ['list'])) {
                $this->parser->addOption('', $baseOptionName, array(
                    'action' => 'store',
                    'default' => null,
                    'dest' => $configField->varName,
                    'help' => $optionHelp
                ));
            } elseif(in_array($configField->type->getTypeName(), ['string', 'text', 'stringlist', 'php'])) {
                $this->parser->addOption('', $baseOptionName, array(
                    'action' => 'store',
                    'default' => null,
                    'dest' => $configField->varName,
                    'help' => $optionHelp
                ));
            } elseif(in_array($configField->type->getTypeName(), ['enum'])) {
                $choices = [];
                $choiceHelp = '';
                if ($optionHelp) {
                    $help = $optionHelp . "\n\nAvailable options:\n";
                } else {
                    $help = "Available options:\n";
                }
                foreach($configField->type->_values as $key => $value) {
                    // Add an option for each enum value.
                    $choices[] = $key;
                    $choiceHelp .= sprintf("  %s: %s\n", $key, $value);
                }   
                $help .= $choiceHelp;
                $this->parser->addOption('', $baseOptionName, array(
                    'action' => 'store',
                    'type' => 'choice',
                    'choices' => $choices,
                    'default' => null,
                    'dest' => $configField->varName,
                    'help' => $help
                ));
            } else {
                //print($configField->type->getTypeName() . ' is not supported yet for CLI options.' . PHP_EOL);
            }
        }
    }
    /**
     * Only get explicitly set CLI values, filtering out null values from defaults.
     */
    public function filteredCliValues(): array
    {
        $filteredValues = [];
        foreach($this->cliValues as $key => $value) {
            // Discern false from unset
            if (!is_null($value)) {
                $filteredValues[$key] = $value;
            }
        }
        return $filteredValues;
    }
    /**
     * Configure the database settings.
     */
    public function configDb(): void
    {
        $this->_cli->writeln();
        $this->_cli->writeln($this->_cli->bold('Configuring database settings'));
        $this->_cli->writeln();

        $sql_config = $this->_config->configSQL('');

        $vars = new Horde_Variables();
        $form = new Horde_Config_Form($vars, 'horde', true);
        $this->_cli->question(
            $vars,
            'sql',
            'phptype',
            $sql_config['switch']['custom']['fields']['phptype'],
            existingAsDefault: true
        );
        $this->writeConfig($vars);
    }

    public function testDbConnection(): bool
    {        
       $db = $GLOBALS['injector']->getInstance('Horde_Db_Adapter');
        try {
            // TODO: The "mysql" adapter blocks forever on an unresolvable hostspec
            $db->connect();
        } catch (Horde_Exception $e) {
            $this->_cli->message('Database connection failed: ' . $e->getMessage(), 'cli.error');
            return false;
        }
        return true;
    }
    public function configAuth() {
        $vars = new Horde_Variables();
        // Ensure we don't lose existing configuration.
        new Horde_Config_Form($vars, 'horde', true);
        // Set default to SQL authentication unless something is already configured.
        $vars->auth__driver ??= 'sql';
        $vars->auth__params__driverconfig ??= 'horde';
        $vars->auth__params__encryption ??= 'ssha';
        $vars->auth__admins ??= '';
        // TODO: Actually configure any custom authentication drivers.
        /*$this->_cli->question(
            $vars,
            'auth',
            'driver',
            $sql_config['switch']['custom']['fields']['driver'],
            existingAsDefault: true
        );*/
        $this->writeConfig($vars);
        // Auth backend should be setup by now, so we can check if the user exists.

        $form = new Horde_Config_Form($vars, 'horde', true);
        $this->_cli->writeln();
        $this->_cli->writeln($this->_cli->bold('Configuring global administrator settings'));
        // TODO: Move getting the factory to constructor
        $auth = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Auth')->create(($is_auth && $vars->app) ? $vars->app : null);
        $atLeastOneAdminExists = false;
        while (!$atLeastOneAdminExists) {
            $adminUsers = $vars->auth__admins ?  explode(', ', $vars->auth__admins) : [];
            if (empty($adminUsers)) {
                $this->_cli->writeln($this->_cli->yellow('No administrator users configured yet.'));
            } else {
                $this->_cli->writeln($this->_cli->yellow('Currently configured administrator users:'));
                foreach ($adminUsers as $user) {
                    $this->_cli->writeln($this->_cli->bold($user));
                    if ($auth->exists($user)) {
                        $this->_cli->writeln($this->_cli->green('User exists in the backend.'));
                        $atLeastOneAdminExists = true;
                    } else {
                        $this->_cli->writeln($this->_cli->red('User does not exist in the backend.'));
                    }
                }
            }
            if (!$atLeastOneAdminExists) {
                $newAdminUser = $this->_cli->prompt('Specify a user name for the administrator account:', $vars, 'administrator');
                if (empty($newAdminUser)) {
                    $this->_cli->writeln($this->_cli->red('An administration user is required'));
                    continue;
                }
                if ($auth->exists($newAdminUser)) {
                    $this->_cli->writeln($this->_cli->green('User already exists in the backend.'));
                    $updatePassword = $this->_cli->prompt('This user exists already, do you want to update his password?', array('y' => 'Yes', 'n' => 'No'), 'y');
                    if ($updatePassword == 'y') {
                        $adminPass = $this->_cli->passwordPrompt('Specify a new password for the administrator account:');
                        if (empty($adminPass)) {
                            $this->_cli->writeln($this->_cli->red('An administrator password is required'));
                            continue;
                        }
                        $auth->updateUser($newAdminUser, $newAdminUser, array('password' => $adminPass));
                    }
                } else {
                    $adminPass = $this->_cli->passwordPrompt('Specify a password for the administrator');
                    $auth->addUser($admin_user, array('password' => $admin_pass));
                }
                $adminUsers[] = $newAdminUser;
                $atLeastOneAdminExists = true;
            }
        }

        $vars->auth__admins = implode(', ', array_unique($adminUsers));
        $this->writeConfig($vars);
    }

    protected function _configAuth(Horde_Variables $vars)
    {   
        return 'administrator';
    }
}