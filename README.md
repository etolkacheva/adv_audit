# Advanced Audit Tool

Drupal 8 auditor tool developed by Adyax.

## Available checkpoints:
### 1. Performance
* Checking php max_execution_time setting
* Cron settings
* ImageAPI Optimize
* Javascript & CSS aggregation
* Memcache/Redis settings
* Performance modules status
* Views performance settings
* Page caching performance
* Database usage
* Solr usage
* Memory usage check
* Page speed insights
### 2. Server Configuration
* Release notes & help files
* PHP Version
* Analyze Watchdog Logs.
* Check Opcache
### 3. Security
* Error are written to the screen.
* PHP register globals
* Trusted Host Settings
* Check if users table contains anonymous user
* Unsafe extensions
* Admin pages access check
* No sensitive temporary files were found.
* Untrusted role's permission
* Security Code Review
* SSL test
* Dangerous Tags
* Checks views are access controlled.
* Check Account settings
* PHP files in public directory cannot be executed.
* Anonymous user rights
* Allowed HTML tags in text formats
* Check must-have modules for security reasons
* Administrator's name check
### 4. Drupal core and contributed modules
* No database updates required
* Modules security updates
* Configuration Manager
* Patched Drupal core.
* Drupal core
* Modules non-security updates
* Patched modules.
* Features status
### 5. Architecture analysis
* Check if CI/CD exists on the project
* Check files structure on project.
* Check if composer is used on the project.
### 6. Code review (custom modules and themes)
* Code audit by CodeSniffer
* Auditing code smells, code complexity. Code metrics * and potential problems
### 7. Other recommendations
* Database tables engine check.
* Check environment settings.
* Checks Seo recommedations: contrib modules and robots.txt.
* Check Ultimate cron module

## Installation

### 1. Prerequisites
To be able to setup this module on your project you will need following 
list of tools:
* composer
* Drupal installation via composer

### Installation process

* Update your project `composer.json` file and add following lines 
to the `repositories` block:
```
{
     "type": "vcs",
     "url": "git@code.adyax.com:Auditor/adv_audit.git"
 }
```
the resulting block should looks like:

```
 "repositories": [
        {
            "type": "composer",
            "url": "https://packages.drupal.org/8"
        },
        {
            "type": "vcs",
            "url": "git@code.adyax.com:Auditor/adv_audit.git"
        }
    ],
```
* Run `composer require drupal/adv_audit` command.
* Specify your gitlab credentials (project hase private status for now)
* Module will be installed to module/contrib directory with all the 
dependencies in project `vendor` folder

### Modules Requirements

Some plugins required appropriate modules. If these modules are not installed on a project, plugins will be skipped. The requirements are set in annotation of each plugin that need it:

```
 *   requirements = {
 *     "module": {
 *       "features",
 *        ...
```
### Instruction for writing a new plugin
####Description for plugin annotation
```
  @AuditPlugin(
   id = "cron_settings",
   label = @Translation("Cron settings"),
   category = "performance",
   requirements = {},
  )
```
* **id** - The plugin ID.
* **label** - The human readable name. 
* **category** - The plugin's category id. All available category described in `/config/install/adv_audit.settings.yml `
* **requirements** - The array of requirements that are need for plugin. Like list of modules, user, configs. If requirements are not met, the plugin will be marked as __SKIPPED__.

Each plugin has own configuration file in `/config/install/adv_audit.plugins.plugin_id.yml` file. This configuration files contain values:

```yaml
messages:
  description: " This is description of plugin jobs"
  actions: "Each key supports %placeholders"
  impacts: ""
  fail: ""
  success: ""
settings:
  enabled: 1|0
  severity: low|normal|high
help: ''
```
* **messages** - messages that are appeared in report depend on plugin's results. Can be overridden in plugin's settings form.
* **settings**
  * **enabled** - Status of the plugin. Can be overridden in plugin's settings form. 
  * **severity** - The default level of severity. Can be overridden in plugin's settings form. 

The main plugin's method `perform()` should return status `success()`, `fail()` or `skipped()`. If the plugin returns failed status the issues should be passed in second argument:
```php
...
      return $this->fail('Reason why plugin has been failed', [
        'issues' => [
          0 => [
            '@issue_title' => 'There are we have some problems in @some_string.',
            '@some_string' => $some_string,
          ],
        ],
        '%link' => Link::createFromRoute($this->t('pass placeholders to messages in config file'), 'needed.route')
          ->toString(),
      ]);
```
The key `@issue_title` is required. Issues support placeholders. Also placeholders can be passed to **messages** as additional elements of the second argument:
```php
[
  'issues' => [$list_of_issues],
  '%placeholder_for_messages',
  '%second_placeholder_for_messages',
  ...,
]
```
