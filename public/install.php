<?php

if (!isset($_SERVER['HTTP_HOST'])) {
    exit('This script cannot be run from the CLI. Run it from a browser.');
}

if (!in_array(@$_SERVER['REMOTE_ADDR'], [
    '127.0.0.1',
    '10.0.2.2',
    '::1',
])) {
    header('HTTP/1.0 403 Forbidden');
    exit('This script is only accessible from localhost.');
}

require_once __DIR__.'/BackBeeRequirements.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
\Symfony\Component\Debug\Debug::enable();

$step = isset($_POST['step']) ? intval($_POST['step']) : 1;

$yaml = new \Symfony\Component\Yaml\Yaml();

switch ($step) {
    case 2:
        /**
         * bootstrap.yml creation
         * -> debug true|false
         * -> container cache directory /path/to/container/cache
         * -> container auto-generate true|false
         */
        if (!isset($_POST['debug']) && !isset($_POST['container_dump_directory'])) {
            $bootstrap_requirements = new BootstrapRequirements();
            $requirements = $bootstrap_requirements->getRequirements();
            break;
        } else {

            $containerDirectory = realpath(__DIR__.'/..').'/cache/container';
            if (!is_dir($containerDirectory)) {
                mkdir($containerDirectory, 755);
            }

            $bootstrap = [
                'debug'     => (bool) intval($_POST['debug']),
                'container' => [
                    'dump_directory' => $containerDirectory,
                    'autogenerate'   => true
                ]
            ];

            file_put_contents(dirname(__DIR__).'/repository/Config/bootstrap.yml', $yaml->dump($bootstrap));

            $step = 3;
        }

    case 3:
        /**
         * doctrine.yml creation
         * dbal:
         *  driver: pdo_mysql|pdo_pgsl|... See (http://doctrine-dbal.readthedocs.org/en/latest/reference/configuration.html#driver)
         *   host: localhost|mysql.domain.com
         *   port: 3306
         *   dbname: myWonderfullWebsite
         *   user: databaseUser
         *   password: databasePassword
         *   charset: utf8 the charset used to connect to the database
         *   collation: utf8_general_ci
         *   defaultTableOptions: { collate: utf8_general_ci, engine: InnoDB, charset: utf8 }
         */
        if (
            isset($_POST['driver'])
            && isset($_POST['engine'])
            && isset($_POST['host'])
            && isset($_POST['port'])
            && isset($_POST['dbname'])
            && isset($_POST['user'])
            && array_key_exists('password', $_POST)
            && isset($_POST['username'])
            && isset($_POST['user_email'])
            && isset($_POST['user_password'])
            && isset($_POST['user_re-password'])
            && ($_POST['user_password'] === $_POST['user_re-password'])
        ) {
            $charset = 'utf8';
            $collation = 'utf8_general_ci';
            $doctrine = [
                'dbal' => [
                    'driver'    => $_POST['driver'],
                    'host'      => $_POST['host'],
                    'port'      => intval($_POST['port']),
                    'dbname'    => $_POST['dbname'],
                    'user'      => $_POST['user'],
                    'password'  => $_POST['password'],
                    'charset'   => $charset,
                    'collation' => $collation,
                    'defaultTableOptions' => [
                        'collate' => $collation,
                        'engine'  => $_POST['engine'],
                        'charset' => $charset
                    ]
                ]
            ];

            file_put_contents(dirname(__DIR__).'/repository/Config/doctrine.yml', $yaml->dump($doctrine));

            $username = $_POST['user'];
            $password = $_POST['password'];
            $dbname = $_POST['dbname'];
            $host = $_POST['host'];
            $port = $_POST['port'];

            try {
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
            } catch (PDOException $ex) {
                try {
                    $conn = mysqli_connect($host, $username, $password, null, $port);
                    mysqli_query(
                        $conn,
                        sprintf(
                            'create database IF NOT EXISTS `%s` character set %s collate %s;',
                            addslashes($dbname),
                            $charset,
                            $collation

                        )
                    );
                } catch (\Exception $e) {
                    echo('Failed to connect to database with the current exception message : '. $e->getMessage());
                    // to be catched by Debug component
                }
            }

            $application = new \BackBee\Standard\Application();

            /**
             * Creation of website skeleton
             * -> Website
             * -> Layouts
             * -> Pages
             * -> Admin user
             */
            try {
                $database = new \BackBee\Installer\Database($application);
                $database->updateBackBeeSchema();
                $database->updateBundlesSchema();
            } catch (\Exception $e) {
                echo('Failed to create or to update database with the current exception message : '. $e->getMessage());
                // to be catched by Debug component
            }

            $entityManager = $application->getEntityManager();
            $connection = $entityManager->getConnection();

            try {

                // Create security Acl tables
                $tablesMapping = [
                    'class_table_name'         => 'acl_classes',
                    'entry_table_name'         => 'acl_entries',
                    'oid_table_name'           => 'acl_object_identities',
                    'oid_ancestors_table_name' => 'acl_object_identity_ancestors',
                    'sid_table_name'           => 'acl_security_identities',
                ];

                foreach ($tablesMapping as $tableName) {
                    $connection->executeQuery(sprintf('DROP TABLE IF EXISTS %s', $tableName));
                }

                $schema = new \Symfony\Component\Security\Acl\Dbal\Schema($tablesMapping);

                $platform = $connection->getDatabasePlatform();

                foreach ($schema->toSql($platform) as $query) {
                    $connection->executeQuery($query);
                }

                /**
                 * Creation of Admin user
                 */
                $encoderFactory = $application->getContainer()->get('security.context')->getEncoderFactory();

                $adminUser = new \BackBee\Security\User($_POST['username'], $_POST['user_password'], 'SuperAdmin', 'SuperAdmin');
                $adminUser
                    ->setApiKeyEnabled(true)
                    ->setActivated(true)
                ;
                $encoder = $encoderFactory->getEncoder($adminUser);
                $adminUser
                    ->setPassword($encoder->encodePassword($_POST['user_password'], ''))
                    ->setEmail($_POST['user_email'])
                    ->generateRandomApiKey()
                ;

                $entityManager->persist($adminUser);
                $entityManager->flush($adminUser);

                $security = \Symfony\Component\Yaml\Yaml::parse(dirname(__DIR__).'/repository/Config/security.yml.dist');
                $security['sudoers'] = [$adminUser->getLogin() => $adminUser->getId()];
                file_put_contents(dirname(__DIR__).'/repository/Config/security.yml', $yaml->dump($security));
                chmod(dirname(__DIR__).'/repository/Config/security.yml', 0755);
                $security = \Symfony\Component\Yaml\Yaml::parse(dirname(__DIR__).'/repository/Config/security.yml');
            } catch (\Exception $e) {
                // to be catched by Debug component
            }

            $step = 4;
        }

        break;

    case 4:
        /**
         * sites.yml creation
         * my-wonderful-website:
         *   label: 'My wonderful website'
         *   domain: my.wonderful-website.com
         *
         */
        $application = new \BackBee\Standard\Application();

        if (isset($_POST['site_name']) && isset($_POST['domain']) && (filter_var($_POST['domain'], FILTER_VALIDATE_URL) !== false)) {

            $em = $application->getEntityManager();
            $pagebuilder = $application->getContainer()->get('pagebuilder');
            $host = parse_url($_POST['domain'],PHP_URL_HOST);
            $port = parse_url($_POST['domain'], PHP_URL_PORT);

            $domain = $host.(empty($port) ? '' : ':'.$port);

            $sites = [
                \BackBee\Utils\String::urlize($_POST['site_name']) => [
                    'label'  => $_POST['site_name'],
                    'domain' => $domain,
                ],
            ];

            file_put_contents(dirname(__DIR__) . '/repository/Config/sites.yml', $yaml->dump($sites));

            foreach ($sites as $label => $siteConfig) {
                // Website creation
                if (null === $site = $em->find('BackBee\Site\Site', md5($label))) {
                    $site = new \BackBee\Site\Site(md5($label));
                    $site
                        ->setLabel($label)
                        ->setServerName($siteConfig['domain'])
                    ;
                    $em->persist($site);
                    $em->flush($site);

                }

                $application->getContainer()->set('site', $site);

                // Home layout
                if (null === $layout = $em->find('BackBee\Site\Layout', md5('defaultlayout-' . $label))) {
                    $layout = new \BackBee\Site\Layout(md5('defaultlayout-' . $label));
                    $layout
                        ->setData('{"templateLayouts":[{"title":"Main Column","layoutSize":{"height":300,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1332943638139_1","layoutClass":"bb4ResizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":5,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","position":"none","height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":true,"accept":[""],"maxentry":"0","defaultClassContent":null}]}')
                        ->setLabel('Home')
                        ->setPath('Home.twig')
                        ->setPicPath($layout->getUid() . '.png')
                        ->setSite($site)
                    ;
                    $em->persist($layout);
                }

                // Article's layout
                if (null === $articleLayout = $em->find('BackBee\Site\Layout', md5('articlelayout-' . $label))) {
                    $articleLayout = new BackBee\Site\Layout(md5('articlelayout-' . $label));
                    $articleLayout
                        ->setData('{"templateLayouts":[{"title":"Main Column","layoutSize":{"height":300,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1332943638139_1","layoutClass":"bb4ResizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":5,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","position":"none","height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":true,"accept":["Article\\\\Article"],"maxentry":"0","defaultClassContent":"Article\\\\Article"},{"title":"Right Pane","layoutSize":{"height":800,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1383430750637_1","layoutClass":"bb5-resizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":2,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","alphaClass":"alpha","omegaClass":"omega","typeClass":"hChild","clearAfter":1,"height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":false,"accept":[],"maxentry":0,"defaultClassContent":null}]}')
                        ->setLabel('Article')
                        ->setPicPath($articleLayout->getUid() . '.png')
                        ->setSite($site)
                    ;
                    $em->persist($articleLayout);
                }

                // Category's layout
                if (null === $categoryLayout = $em->find('BackBee\Site\Layout', md5('categorylayout-' . $label))) {
                    $categoryLayout = new BackBee\Site\Layout(md5('categorylayout-' . $label));
                    $categoryLayout
                        ->setData('{"templateLayouts":[{"title":"Main column","layoutSize":{"height":300,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1332943638139_1","layoutClass":"bb4ResizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":5,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","position":"none","height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":true,"accept":["Article\\\\CategoryList"],"maxentry":"0","defaultClassContent":"Article\\\\CategoryList"},{"title":"Second column","layoutSize":{"height":800,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1383430750637_1","layoutClass":"bb5-resizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":2,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","alphaClass":"alpha","omegaClass":"omega","typeClass":"hChild","clearAfter":1,"height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":false,"accept":[],"maxentry":0,"defaultClassContent":null},{"title": "Nouvelle zone","layoutSize": {"height": 800,"width": false},"gridSizeInfos": {"colWidth": 60,"gutterWidth": 20},"id": "Layout__1383430750640_1","layoutClass": "bb5-resizableLayout","animateResize": false,"showTitle": false,"target": "#bb5-mainLayoutRow","resizable": true,"useGridSize": true,"gridSize": 2,"gridStep": 100,"gridClassPrefix": "span","selectedClass": "bb5-layout-selected","alphaClass": "alpha","omegaClass": "omega","typeClass": "hChild","clearAfter": 1,"height": 800,"defaultContainer": "#bb5-mainLayoutRow","layoutManager": [],"mainZone": false,"accept": [],"maxentry": 0,"defaultClassContent": null}]}')
                        ->setLabel('Category')
                        ->setPicPath($categoryLayout->getUid() . '.png')
                        ->setSite($site)
                    ;
                    $em->persist($categoryLayout);
                }

                // Creating site root page
                if (null === $root = $em->find('BackBee\NestedNode\Page', md5('root-' . $label))) {
                    $pagebuilder
                        ->setUid(md5('root-' . $label))
                        ->setTitle('Home')
                        ->setLayout($layout)
                        ->setSite($site)
                        ->setUrl('/')
                        ->putOnlineAndHidden()
                    ;

                    $page = $pagebuilder->getPage();
                    $em->persist($page);
                    $em->flush($page);

                    $blockDemo = new \BackBee\ClassContent\BlockDemo();
                    $blockDemo->setState(\BackBee\ClassContent\AbstractClassContent::STATE_NORMAL);
                    $blockDemo->setRevision(1);
                    $homeContainer = new \BackBee\ClassContent\Home\HomeContainer();
                    $homeContainer->setState(\BackBee\ClassContent\AbstractClassContent::STATE_NORMAL);
                    $homeContainer->setRevision(1);
                    $homeContainer->container->setState(\BackBee\ClassContent\AbstractClassContent::STATE_NORMAL);
                    $homeContainer->container->setRevision(1);
                    $homeContainer->container->push($blockDemo);
                    $page->getContentSet()->first()->push($homeContainer);
                }

                // Creating mediacenter root
                if (null === $mediafolder = $em->find('BackBee\NestedNode\MediaFolder', md5('media'))) {
                    $mediafolder = new \BackBee\NestedNode\MediaFolder(md5('media'));
                    $mediafolder->setTitle('Mediacenter')->setUrl('/');
                    $em->persist($mediafolder);
                }

            }

            if (null === $em->find('BackBee\NestedNode\KeyWord', md5('root'))) {
                $keyword = new \BackBee\NestedNode\KeyWord(md5('root'));
                $keyword->setRoot($keyword);
                $keyword->setKeyWord('root');
                $em->persist($keyword);
            }

            $em->flush();
            $step = 5;

            $yamlParser = new \Symfony\Component\Yaml\Parser();
            $groups = $yamlParser->parse(file_get_contents(\BackBee\Standard\Application::getConfigurationDir() . 'groups.yml'));

            foreach ($groups as $groupName => $rights) {
                $group = new \BackBee\Security\Group();
                $group->setName($groupName);
                if (array_key_exists('description', $rights)) {
                    $group->setDescription($rights['description']);
                    unset($rights['description']);
                }
                $group->setSite($site);
                $em->persist($group);
                $em->flush($group);

                setSiteGroupRights($site, $group, $rights);
            }
        }

        $containerDumpDir = $application->getContainer()->getParameter('container.dump_directory');
        foreach (glob($containerDumpDir.DIRECTORY_SEPARATOR.'*') as $file) {
             if (is_file($file)) {
                unlink($file);
             }
         }

        break;

    case 1:
    default:
        $backbeeRequirements = new BackBeeRequirements();
        $requirements = $backbeeRequirements->getRequirements();
}

/**
 * Returns all categorized class content names for the given application
 * @param \BackBee\ApplicationInterface $application
 * @return String[]
 */
function getAllContentClasses(\BackBee\ApplicationInterface $application)
{
    $allClasses = [];
    $categoryManager = new \BackBee\ClassContent\CategoryManager($application);
    foreach ($categoryManager->getCategories() as $category) {
        $blocks = array_map(
            function($block) {
                return \BackBee\ClassContent\AbstractContent::CLASSCONTENT_BASE_NAMESPACE.str_replace('/', NAMESPACE_SEPARATOR, $block->type);
            },
            $category->getBlocks()
        );
        $allClasses = array_merge($allClasses, $blocks);
    }

    return $allClasses;
}

function setSiteGroupRights($site, $group, $rights)
{
    $application = new \BackBee\Standard\Application();
    $em = $application->getEntityManager();
    $aclProvider = $application->getSecurityContext()->getACLProvider();
    $securityIdentity = new \Symfony\Component\Security\Acl\Domain\UserSecurityIdentity($group->getObjectIdentifier(), 'BackBee\Security\Group');

    if (array_key_exists('sites', $rights)) {
        $sites = addSiteRights($rights['sites'], $aclProvider, $securityIdentity, $site);
        if (array_key_exists('layouts', $rights)) {
            addLayoutRights($rights['layouts'], $aclProvider, $securityIdentity, $site, $em);
        }

        if (array_key_exists('pages', $rights)) {
            addPageRights($rights['pages'], $aclProvider, $securityIdentity, $em);
        }

        if (array_key_exists('mediafolders', $rights)) {
            addFolderRights($rights['mediafolders'], $aclProvider, $securityIdentity);
        }

        if (array_key_exists('contents', $rights)) {
            addContentRights($rights['contents'], $aclProvider, $securityIdentity, getAllContentClasses($application));
        }

        if (array_key_exists('bundles', $rights)) {
            addBundleRights($rights['bundles'], $aclProvider, $securityIdentity, $application);
        }

        if (array_key_exists('users', $rights)) {
            addUserRights($rights['users'], $aclProvider, $securityIdentity);
        }

        if (array_key_exists('groups', $rights)) {
            addGroupRights($rights['groups'], $aclProvider, $securityIdentity);
        }

        return $sites;
    }
}

function getActions($definition)
{
    $actions = [];
    if (is_array($definition)) {
        $actions = array_intersect(['view', 'create', 'edit', 'delete', 'publish'], $definition);
    } elseif ('all' === $definition) {
        $actions = ['view', 'create', 'edit', 'delete', 'publish'];
    }

    return $actions;
}

function addSiteRights($sitesDefinition, $aclProvider, $securityIdentity, $site)
{
    if (!array_key_exists('resources', $sitesDefinition) || !array_key_exists('actions', $sitesDefinition)) {
        return [];
    }

    $actions = getActions($sitesDefinition['actions']);

    if (0 === count($actions)) {
        return [];
    }

    if (is_array($sitesDefinition['resources']) && in_array($site->getLabel(), $sitesDefinition['resources'])) {
        addObjectAcl($site, $aclProvider, $securityIdentity, $actions);
    } elseif ('all' === $sitesDefinition['resources']) {
        addClassAcl('BackBee\Site\Site', $aclProvider, $securityIdentity, $actions);
    }
}

function addUserRights($userDef, $aclProvider, $securityIdentity)
{
    if (!array_key_exists('resources', $userDef) || !array_key_exists('actions', $userDef)) {
        return [];
    }

    $actions = getActions($userDef['actions']);
    if (0 === count($actions)) {
        return [];
    }

    if (is_array($userDef['resources'])) {
        foreach ($userDef['resources'] as $userId) {
            $user = $em->getRepository('BackBee\Security\User')->findBy(['_id' => $userId]);

            addObjectAcl($user, $aclProvider, $securityIdentity, $actions);
        }
    } elseif ('all' === $userDef['resources']) {
        addClassAcl('BackBee\\Security\\User', $aclProvider, $securityIdentity, $actions);
    }
}

function addGroupRights($groupDef, $aclProvider, $securityIdentity)
{
    if (!array_key_exists('resources', $groupDef) || !array_key_exists('actions', $groupDef)) {
        return [];
    }

    $actions = getActions($groupDef['actions']);
    if (0 === count($actions)) {
        return [];
    }

    if (is_array($groupDef['resources'])) {
        foreach ($groupDef['resources'] as $group_id) {
            $group = $em->getRepository('BackBee\Security\Group')->findBy(array('_id' => $group_id));

            addObjectAcl($group, $aclProvider, $securityIdentity, $actions);
        }
    } elseif ('all' === $groupDef['resources']) {
        addClassAcl('BackBee\\Security\\Group', $aclProvider, $securityIdentity, $actions);
    }
}

function addLayoutRights($layoutDefinition, $aclProvider, $securityIdentity, $site, $em)
{
    if (!array_key_exists('resources', $layoutDefinition) || !array_key_exists('actions', $layoutDefinition)) {
        return;
    }

    $actions = getActions($layoutDefinition['actions']);
    if (0 === count($actions)) {
        return array();
    }

    if (is_array($layoutDefinition['resources'])) {
        foreach ($layoutDefinition['resources'] as $layoutLabel) {
            if (null === $layout = $em->getRepository('BackBee\Site\Layout')->findOneBy(['_site' => $site, '_label' => $layoutLabel])) {
                continue;
            }

            addObjectAcl($layout, $aclProvider, $securityIdentity, $actions);
        }
    } elseif ('all' === $layoutDefinition['resources']) {
        addClassAcl('BackBee\Site\Layout', $aclProvider, $securityIdentity, $actions);
    }
}

function addPageRights($pageDefinition, $aclProvider, $securityIdentity, $em)
{
    if (!array_key_exists('resources', $pageDefinition) || !array_key_exists('actions', $pageDefinition)) {
        return;
    }

    $actions = getActions($pageDefinition['actions']);
    if (0 === count($actions)) {
        return [];
    }

    if (is_array($pageDefinition['resources'])) {
        foreach ($pageDefinition['resources'] as $pageUrl) {
            $pages = $em->getRepository('BackBee\NestedNode\Page')->findBy(['_url' => $pageUrl]);
            foreach ($pages as $page) {
                addObjectAcl($page, $aclProvider, $securityIdentity, $actions);
            }
        }
    } elseif ('all' === $pageDefinition['resources']) {
        addClassAcl('BackBee\NestedNode\Page', $aclProvider, $securityIdentity, $actions);
    }
}

function addFolderRights($folderDefinition, $aclProvider, $securityIdentity)
{
    if (!array_key_exists('resources', $folderDefinition) || !array_key_exists('actions', $folderDefinition)) {
        return;
    }

    $actions = getActions($folderDefinition['actions']);
    if (0 === count($actions)) {
        return [];
    }

    if ('all' === $folderDefinition['resources']) {
        addClassAcl('BackBee\NestedNode\MediaFolder', $aclProvider, $securityIdentity, $actions);
    }
}

function addContentRights($contentDefinition, $aclProvider, $securityIdentity, $allClasses = [])
{
    if (!array_key_exists('resources', $contentDefinition) || !array_key_exists('actions', $contentDefinition)) {
        return;
    }

    if ('all' === $contentDefinition['resources']) {
        $actions = getActions($contentDefinition['actions']);
        if (0 === count($actions)) {
            return [];
        }
        addClassAcl('BackBee\ClassContent\AbstractClassContent', $aclProvider, $securityIdentity, $actions);
    } elseif (is_array($contentDefinition['resources']) && 0 < count($contentDefinition['resources'])) {
        if (is_array($contentDefinition['resources'][0])) {
            $usedClasses = [];
            foreach ($contentDefinition['resources'] as $index => $resourcesDefinition) {
                if (!isset($contentDefinition['actions'][$index])) {
                    continue;
                }

                $actions = getActions($contentDefinition['actions'][$index]);

                if ('remains' === $resourcesDefinition) {
                    foreach ($allClasses as $className) {
                        if (!in_array($className, $usedClasses)) {
                            $usedClasses[] = $className;
                            if (0 < count($actions)) {
                                addClassAcl($className, $aclProvider, $securityIdentity, $actions);
                            }
                        }
                    }
                } elseif (is_array($resourcesDefinition)) {
                    foreach ($resourcesDefinition as $content) {
                        $className = '\BackBee\ClassContent\\'.$content;
                        if (substr($className, -1) === '*') {
                            $className = substr($className, 0 - 1);
                            foreach ($allClasses as $fullClass) {
                                if (0 === strpos($fullClass, $className)) {
                                    $usedClasses[] = $fullClass;
                                    if (0 < count($actions)) {
                                        addClassAcl($fullClass, $aclProvider, $securityIdentity, $actions);
                                    }
                                }
                            }
                        } elseif (class_exists($className)) {
                            $usedClasses[] = $className;
                            if (0 < count($actions)) {
                                addClassAcl($className, $aclProvider, $securityIdentity, $actions);
                            }
                        }
                    }
                }
            }
        } else {
            $actions = getActions($contentDefinition['actions']);
            if (0 === count($actions)) {
                return [];
            }

            foreach ($contentDefinition['resources'] as $content) {
                $className = '\BackBee\ClassContent\\'.$content;
                if (substr($className, -1) === '*') {
                    $className = substr($className, 0 -1);
                    foreach($allClasses as $fullClass) {
                        if (0 === strpos($fullClass, $className)) {
                            addClassAcl($fullClass, $aclProvider, $securityIdentity, $actions);
                        }
                    }
                } elseif (class_exists($className)) {
                    addClassAcl($className, $aclProvider, $securityIdentity, $actions);
                }
            }
        }
    }
}

function addBundleRights($bundleDefinition, $aclProvider, $securityIdentity, $application)
{

    if (!array_key_exists('resources', $bundleDefinition) || !array_key_exists('actions', $bundleDefinition)) {
        return;
    }

    $actions = getActions($bundleDefinition['actions']);
    if (0 === count($actions)) {
        return [];
    }

    if (is_array($bundleDefinition['resources'])) {
        foreach ($bundleDefinition['resources'] as $bundleName) {
            if (null !== $bundle = $application->getBundle($bundleName)) {
                addObjectAcl($bundle, $aclProvider, $securityIdentity, $actions);
            }
        }
    } elseif ('all' === $bundleDefinition['resources']) {
        foreach ($application->getBundles() as $bundle) {
            addObjectAcl($bundle, $aclProvider, $securityIdentity, $actions);
        }
    }
}

function addClassAcl($className, $aclProvider, $securityIdentity, $rights)
{
    $objectIdentity = new Symfony\Component\Security\Acl\Domain\ObjectIdentity('all', $className);
    addAcl($objectIdentity, $aclProvider, $securityIdentity, $rights);
}

function addObjectAcl($object, $aclProvider, $securityIdentity, $rights)
{
    $objectIdentity = Symfony\Component\Security\Acl\Domain\ObjectIdentity::fromDomainObject($object);
    addAcl($objectIdentity, $aclProvider, $securityIdentity, $rights);
}

function addAcl($objectIdentity, $aclProvider, $securityIdentity, $rights)
{
    // Getting ACL for this object identity
    try {
        $acl = $aclProvider->createAcl($objectIdentity);
    } catch (\Exception $e) {
        $acl = $aclProvider->findAcl($objectIdentity);
    }

    // Calculating mask
    $builder = new \BackBee\Security\Acl\Permission\MaskBuilder();
    foreach ($rights as $right) {
        $builder->add($right);
    }
    $mask = $builder->get();

    // first revoke existing access for this security identity
    foreach($acl->getObjectAces() as $i => $ace) {
        if($securityIdentity->equals($ace->getSecurityIdentity())) {
            $acl->updateObjectAce($i, $ace->getMask() & ~$mask);
        }
    }

    // then grant
    $acl->insertClassAce($securityIdentity, $mask);
    $aclProvider->updateAcl($acl);
}
?>

<!doctype html>

<html lang="en">
    <head>
        <meta charset="utf-8">

        <title>BackBee Standard installation</title>
        <meta name="description" content="BackBee CMS Standard web installer">
        <meta name="author" content="Lp digital, BackBee community">

        <link rel="stylesheet" href="css/installer.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">

        <!--[if lt IE 9]>
            <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <link rel="icon" href="favicon.ico">
        <style>
            div.alert strong {
                display: inline-block;
                width: 350px;
            }

            form label {
                display: block;
            }

            div.steps-container {
                background-color: #f4f4f4;
                border: 1px solid #e1e1e1;
                border-top: 0;
                padding: 10px 20px;
            }

            div.steps-container h2 {
                border-bottom: 2px solid #e54a46;
                color: #e54a46;
                margin: 10px 0 20px 0;
                padding: 0 0 5px 0;
            }

            div.cover-header {
                padding: 3px 0 0 26px !important;
                height: 87px !important;
            }
        </style>

    </head>

    <body>

        <div id="bb5-site-wrapper">
            <div class="cover-container">
                <div class="inner cover">
                    <div class="cover-header">
                        <h1 class="masthead-brand"><img src="img/logo.png" alt="BackBee"> Installer</h1>
                    </div>

                    <?php if (1 === $step): ?>
                        <div class="cover-body">
                            <div class="welcome">
                                <h2 class="welcome-heading">Welcome to <span>BackBee Installation</span></h2>

                                <p>In order to install BackBee properly, we need to check if your system fulfills all the requirements.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="steps-container">

                    <?php if (2 === $step): ?>

                        <h2>Step 2 - General application configuration</h2>

                        <div>
                            <?php $success = true; ?>
                            <?php foreach ($requirements as $requirement): ?>
                                <div class="alert <?php echo ($requirement->isOk() ? 'alert-success' : 'alert-danger'); ?>">
                                    <strong><?php echo $requirement->getTitle(); ?></strong> <?php echo (true === $requirement->isOk() ? 'OK' : $requirement->getErrorMessage()); ?>
                                </div>
                                <?php $success = $success && $requirement->isOk(); ?>
                            <?php endforeach; ?>
                        </div>

                        <div>
                            <?php if (false === $success): ?>
                                <form action="" method="POST">
                                    <input type="hidden" name="step" value="2" />
                                    <input type="submit" class="btn btn-primary" value="Check again" />
                                </form>
                            <?php else: ?>
                                <form action="" method="POST" role="form" class="form-inline">
                                    <div class="form-group">
                                        <label for="debug" >Developer mode ?</label>
                                        <select name="debug" id="debug" class="form-control">
                                            <option value="0" selected>false</option>
                                            <option value="1">true</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="step" value="2" />
                                    <div class="text-right">
                                        <input type="submit" class="btn btn-primary" value="Save it and go to step 3" />
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>

                    <?php elseif (3 === $step): ?>

                        <h2>Step 3 - Database configuration</h2>

                            <form action="" method="POST" role="form">
                                <input type="hidden" name="step" value="3" />

                                <div class="form-group">
                                    <label for="driver">Database</label>
                                    <select class="form-control" name="driver" id="driver">
                                        <option value="pdo_mysql" selected>MySQL</option>
                                        <option value="pdo_pgsql">PostgreSQL</option>
                                        <option value="pdo_sqlite">SQLite</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="engine">DataBase storage engine</label>
                                    <select class="form-control" name="engine" id="engine">
                                        <option value="InnoDB" selected>InnoDB</option>
                                        <option value="MyISAM">MyISAM</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="host">Database host</label>
                                    <input type="text" class="form-control" name="host" placeholder="localhost" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="port">Database port</label>
                                    <input type="text" class="form-control" name="port" value="3306" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="dbname">Database name</label>
                                    <input type="text" class="form-control" name="dbname" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="user">Database user username</label>
                                    <input type="text" class="form-control" name="user" placeholder="root" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="password">Database user password</label>
                                    <input type="password" class="form-control" name="password" />
                                </div>

                                <h2>Admin user configuration</h2>

                                <div class="form-group">
                                    <label for="username">username</label>
                                    <input type="text" pattern=".{6,}" required title="6 characters at least" class="form-control" name="username" placeholder="John Doe" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="user_email">email</label>
                                    <input type="email" class="form-control" name="user_email" placeholder="john.doe@backbee.com" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="user_password">password</label>
                                    <input type="password" pattern=".{6,}" required title="6 characters at least" class="form-control" name="user_password" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="user_re-password">confirm password</label>
                                    <input type="password" pattern=".{6,}" required title="6 characters at least" class="form-control" name="user_re-password" required="required" />
                                </div>


                                <div class="text-right">
                                    <input type="submit" class="btn btn-primary" value="Save it and go to step 4" />
                                </div>
                            </form>

                    <?php elseif (4 === $step): ?>

                        <h2>Step 4 - Site configuration</h2>

                        <div>
                            <form action="" method="POST" role="form">
                                <input type="hidden" name="step" value="4" />

                                <div class="form-group">
                                    <label for="site_name">Site name</label>
                                    <input type="text" class="form-control" name="site_name" placeholder="My wonderful website" required="required" />
                                </div>

                                <div class="form-group">
                                    <label for="domain">Site domain</label>
                                    <input type="url" class="form-control" name="domain" placeholder="my-wonderful-website.com" required="required" />
                                </div>

                                <div class="text-right">
                                    <input type="submit" class="btn btn-primary" value="Save it and complete installation" />
                                </div>
                            </form>
                        </div>

                    <?php elseif (5 === $step): ?>
                        <h2>Installation completed</h2>

                        <p>Example of nginx virtual host:</p>
                        <?php $site = array_shift($sites); ?>
                        <pre>
# example of nginx virtual host for BackBee project
server {
    listen 80;

    server_name <?php echo $site['domain']; ?>;
    root <?php echo __DIR__ . '/'; ?>;

    error_log /var/log/nginx/<?php echo \BackBee\Utils\String::urlize($site['label']); ?>.error.log;
    access_log /var/log/nginx/<?php echo \BackBee\Utils\String::urlize($site['label']); ?>.access.log;

    location ~ /resources/(.*) {
        alias <?php echo dirname(__DIR__) . '/'; ?>;
        try_files /repository/Resources/$1 /vendor/backbee/backbee/Resources/$1 @rewriteapp;
    }

    location @emptygif404 { empty_gif; }

    location / {
        try_files $uri @rewriteapp;
    }

    location @rewriteapp {
        rewrite ^(.*)$ /index.php?$query_string last;
    }

    location ~ ^/(install|index)\.php(/|$) {
        fastcgi_pass unix:/var/run/php5-fpm.sock;
        include fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}</pre>
                    <p>Example of apache2 virtual host:</p>
                    <pre>
# example of apache2 virtual host for BackBee project
&lt;VirtualHost *:80&gt;
    ServerName <?php echo $site['domain']; ?>

    DocumentRoot <?php echo __DIR__ . '/'; ?>

    RewriteEngine On

    &lt;Directory <?php echo str_replace('public', '', __DIR__); ?> &gt;
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Order allow,deny
        allow from all
    &lt;/Directory&gt;

    RewriteCond %{DOCUMENT_ROOT}/../repository/Resources/$1 -f
    RewriteRule ^/resources/(.*)$ %{DOCUMENT_ROOT}/../repository/Resources/$1 [L]

    RewriteCond %{DOCUMENT_ROOT}/../vendor/backbee/backbee/Resources/$1 -f
    RewriteRule ^/resources/(.*)$ %{DOCUMENT_ROOT}/../vendor/backbee/backbee/Resources/$1 [L]

    RewriteCond %{DOCUMENT_ROOT}/../repository/Data/Storage/$1/$2.$4 -f
    RewriteRule ^/images/([a-f0-9]{3})/([a-f0-9]{29})/(.*)\.([^\.]+)$ %{DOCUMENT_ROOT}/../repository/Data/Storage/$1/$2.$4 [L]

    RewriteCond %{DOCUMENT_ROOT}/../repository/Data/Media/$1/$2.$4 -f
    RewriteRule ^/images/([a-f0-9]{3})/([a-f0-9]{29})/(.*)\.([^\.]+)$ %{DOCUMENT_ROOT}/../repository/Data/Media/$1/$2.$4 [L]

    RewriteCond %{DOCUMENT_ROOT}/../repository/Data/Storage/$1 -f
    RewriteRule ^images/(.*)$ %{DOCUMENT_ROOT}/../repository/Data/Storage/$1 [L]

    RewriteCond %{DOCUMENT_ROOT}/../repository/Data/Media/$1 -f
    RewriteRule ^images/(.*)$ %{DOCUMENT_ROOT}/../repository/Data/Media/$1 [L]
&lt;/VirtualHost&gt;
                    </pre>

                    <p class="text-center">
                        <a href="<?php echo 1 === preg_match('#^http#', $site['domain']) ? $site['domain'] : 'http://' . $site['domain']; ?>" class="btn btn-success btn-lg" target="_blank">
                            <strong>Runs <?php echo ucfirst($site['label']); ?></strong>
                        </a>
                    </p>

                    <?php else: ?>

                        <div>
                            <h2>Step 1 - Requirements checks</h2>

                            <?php $success = true; ?>
                            <?php foreach ($requirements as $requirement): ?>
                                <div class="alert <?php echo (true === $requirement->isOk() ? 'alert-success' : 'alert-danger'); ?>">
                                    <strong><?php echo $requirement->getTitle(); ?></strong> <?php echo (true === $requirement->isOk() ? 'OK' : $requirement->getErrorMessage()); ?>
                                </div>
                                <?php $success = $success && $requirement->isOk(); ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-right">
                            <?php if (true === $success): ?>
                                <form action="" method="POST">
                                    <input type="hidden" name="step" value="2" />
                                    <input type="submit" class="btn btn-primary" value="Go to step 2" />
                                </form>
                            <?php else: ?>
                                <strong>Resolve issues listed above and go to step 2</strong>
                            <?php endif; ?>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>

    </body>
</html>
