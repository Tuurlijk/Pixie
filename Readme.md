# Pixie
Pixie is a tool for managing a local development environment contained in a Virtual machine. Pixie enables you to transfer files and databases from a remote machine (that you have access to) to the local machine. You can do this by hand by using tools like ssh, mysqldump and rsync. Pixie makes this task easier by providing a way to store the configuration of your machines in a readable yaml file and shortening the commands.

## Building the executable
This project uses the [Box Framework](https://github.com/box-project/box2). The Box application simplifies the Phar building process. So go ahead and install that.

Then from the project directory do a `box build`. This will create an executable pixie.phar. You can move this where you like.

## Available commands

### Site
The `site` command will list the available configurations:

```bash
./pixie.phar site
+-----------+--------------------------------+
| localhost                                  |
+-----------+--------------------------------+
| host      |                                |
| username  |                                |
| root      | '~/Projects/TYPO3/Development' |
| directory |                                |
+-----------+--------------------------------+
| homestead                                  |
+-----------+--------------------------------+
| host      | 'local.typo3.org'              |
| username  | 'vagrant'                      |
| root      | '/var/www'                     |
| directory |                                |
+-----------+--------------------------------+
| example                                    |
+-----------+--------------------------------+
| host      | 'some.dev.pixie.io'            |
| username  | 'www-data'                     |
| root      | '/var/www'                     |
| directory | 'some.dev.pixie.io'            |
+-----------+--------------------------------+
```

### Sync
The `sync` command can synchronize TYPO3 installations between servers. It takes the configuration keys as source and target parameters. The command: `./pixie.phar sync dev local`, would transfer all the files and the database from the `dev` environment to the `local` environment.

#### Dry run
To show what would be synchronised, but not actually do anything: `./pixie.phar sync dev local --dry`

#### Interactive mode
When no parameters are given, you can choose the source and target interactively.

#### Skip database
You can kip synchronising the database by adding the `--skip-database` option: `./pixie.phar sync dev local --skip-database`.

#### Skip files
You can kip synchronising the files by adding the `--skip-files` option: `./pixie.phar sync dev local --skip-files`.

## Minimum viable product
* ✔ Configuration file format
* ✔ Read configuration file
* ✔ Execute commands using locally available commands (rsync, ssh)
* ✔ ((OS|Linu|X)|BSD) support 

## Phase 2
* ✔ database backup and import

## Phase 3
* Check reachability of configuration. E.g. if the source can rsync to the target or not.
* Edit configuration file using pixie

## Rsync
Synchronising files is done using rsync. It is not possible to rsync between two remote urls directly. The purpose of this tool is to enable synchronising local development environments with remote development environments. A local development environment can be the host machine or a virtual machine running on the host.
 
The remote development environment is usually reachable from the host, but not the other way around. To transfer files from the remote to the local virtual machine environment, we can first ssh into the virtual machine and then execute a rsync command from there. This will work if we have ssh agent forwarding enabled for the virtual machine.

```
Host *.local.typo3.org local.typo3.org 192.168.144.120
        StrictHostKeyChecking no
        UserKnownHostsFile=/dev/null
        ForwardAgent yes
        User vagrant
```

If no 'host' property is enabled for a target site configuration, the files will be transferred using only rsync. Pixie always expects the target to be the host system.
