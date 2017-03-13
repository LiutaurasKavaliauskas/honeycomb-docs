<?php
namespace interactivesolutions\honeycombdocs\app\console\commands;

use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionMethod;
use interactivesolutions\honeycombcore\commands\HCCommand;
use Nette\Reflection\AnnotationsParser;
use phpDocumentor\Reflection\File;
use Symfony\Component\Finder\Finder;

class HCDocs extends HCCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hc:docs {path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates DOC.html file of the given class';

    /**
     * Execute the console command.
     *
     * @return class $info
     */
    public function handle()
    {
        if (!file_exists(public_path('docs')))
            $this->createWebsiteFrame();
        else {
            $purge = $this->ask('Do you want to delete old docs?', 'y/n');

            if ($purge == 'y' || $purge == 'yes') {
                $this->deleteDirectory(public_path('docs'), true);
                $this->createWebsiteFrame();
            }
        }

        if (!$this->argument('path')) {
            if (app()->environment() == 'local') {

                $files = $this->getConfigFiles();

                // removing project config
                array_pop($files);

                foreach ($files as $file)
                    if (strpos($file, '/vendor/') === false)
                        $this->generatePackageDocs(realpath(implode('/', array_slice(explode('/', $file), 0, -4))) . '/');
            }
        } else
            $this->generatePackageDocs($this->argument('path'));
    }

    /**
     * Generating single pacakge docs
     *
     * @param $path - path to package origin
     */
    private function generatePackageDocs($path)
    {
        $this->comment($path);

        $classesInfo = null;

        foreach ($this->getPhpFiles($path) as $parsedClass) {
            $className = AnnotationsParser::parsePhp(file_get_contents($parsedClass));

            if (sizeof($className) == 0)
                continue;

            $class = new \Go\ParserReflection\ReflectionClass(array_keys($className)[0]);


            if (strpos($parsedClass->getRelativePath(), 'commands') != false || strpos($parsedClass->getRelativePath(), 'console/commands') != false) {
                $info = ([
                    'classInfo'        => $this->getClassInfo($class),
                    'classType'        => $this->getClassType($class),
                    'classInheritance' => $this->getClassInheritance($class),
                    'classProperties'  => $this->getClassProperties($class),
                    'commandsInfo'     => $this->getCommands($class)
                ]);
                $classesInfo['commands'][] = $info;
            } elseif ((strpos($parsedClass->getRelativePath(), 'controllers') != false) && (strpos($parsedClass->getRelativePath(), 'controllers/traits') != true)) {
                $info = ([
                    'classInfo'        => $this->getClassInfo($class),
                    'classType'        => $this->getClassType($class),
                    'classInheritance' => $this->getClassInheritance($class),
                    'classProperties'  => $this->getClassProperties($class),
                    'classMethods'     => $this->getClassMethods($class)
                ]);
                $classesInfo['controllers'][] = $info;

            } elseif (strpos($parsedClass->getRelativePath(), 'middleware') != false) {
                $info = ([
                    'classInfo'        => $this->getClassInfo($class),
                    'classType'        => $this->getClassType($class),
                    'classInheritance' => $this->getClassInheritance($class),
                    'classProperties'  => $this->getClassProperties($class),
                    'classMethods'     => $this->getClassMethods($class)
                ]);
                $classesInfo['middleware'][] = $info;

            }
        }

        if ($classesInfo) {
            $this->createDocFile($classesInfo, $path);
            $this->createIndexFile();
        }
<<<<<<< HEAD
        else
            $this->error('There are no controllers, commands or middleware for package - ' . $path);
=======

        $this->createDocFile($classesInfo);
        $this->createIndexFile();
>>>>>>> origin/master
    }

    public function createIndexFile()
    {
        $dir = base_path() . "/public/docs/";
        $files = scandir($dir);
        $template = $this->file->get(__DIR__ . '/templates/docs/packageBlock.hctpl');
        $output = '';

        foreach ($files as $file)
            if (substr($file, -5) == ".html" && $file != "index.html")
            {
                $fileName = (explode('-',$file));
                unset($fileName[0]);
                $fileName = substr(implode(' ', $fileName),0 , -5);
                $field = str_replace('{packageName}', ucfirst($fileName), $template);
                $field = str_replace('{file}', $file, $field);
                $output .= $field;
            }

        $this->createFileFromTemplate([
            "destination"         => base_path('public/docs/index.html'),
            "templateDestination" => __DIR__ . '/templates/docs/index.hctpl',
            "content"             => [
                "packageBlock" => $output
            ],
        ]);
    }


    /**
     * @param $classesInfo
     * @param $value
     * @param $file
     * @return mixed
     */
    public function createMethodRow($classesInfo, $value, $file)
    {
        $field = str_replace('{methodName}', $value['method'], $file);
        $field = str_replace('{methodDescription}', $value['comment'], $field);
        $field = str_replace('{className}', $classesInfo['classInfo']['className'], $field);
        return $field;

    }

    /**
     * @param array $classesInfo
     * @return string
     */
    public function createControllerMenu(array $classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/controllersMenu.hctpl');
        $output = '';

        if (isset($classesInfo['controllers']))
            foreach ($classesInfo['controllers'] as $value) {
                $field = str_replace('{className}', $value['classInfo']['className'], $file);
                $output .= $field;
            }

        return $output;
    }

    /**
     * @param array $classesInfo
     * @return string
     */
    public function createControllerRow(array $classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/controllersRow.hctpl');
        $output = '';
        if (isset($classesInfo['controllers'])) {
            foreach ($classesInfo['controllers'] as $value) {
                $field = str_replace('{packageName}', $value['classInfo']['name'], $file);
                $field = str_replace('{className}', $value['classInfo']['className'], $field);
                $field = str_replace('{classInheritance}', implode(' &#8594 ', $value['classInheritance']), $field);
                $field = str_replace('{methods}', $this->controllerPublicMethods($value), $field);
                $field = str_replace('{privateMethods}', $this->controllerPrivateMethods($value), $field);
                $field = str_replace('{protectedMethods}', $this->controllerProtectedMethods($value), $field);

                $output .= $field;
            }

        }

        return $output;
    }

    /**
     * @param array $classesInfo
     * @return string
     */
    public function controllerPublicMethods(array $classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/controllersMethod.hctpl');
        $output = '';

        if (isset($classesInfo['classMethods']['publicMethods']))
            foreach ($classesInfo['classMethods']['publicMethods'] as $value) {
                if ($value != null) {
                    $output .= $this->createMethodRow($classesInfo, $value, $file);
                }
            }
        return $output;
    }

    /**
     * @param array $classesInfo
     * @return string
     */
    public function controllerPrivateMethods(array $classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/controllersMethodPrivate.hctpl');
        $output = '';

        if (isset($classesInfo['classMethods']['privateMethods']))
            foreach ($classesInfo['classMethods']['privateMethods'] as $value) {
                if ($value != null) {
                    $output .= $this->createMethodRow($classesInfo, $value, $file);
                }
            }
        return $output;
    }

    /**
     * @param array $classesInfo
     * @return string
     */
    public function controllerProtectedMethods(array $classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/controllersMethodProtected.hctpl');
        $output = '';

        if (isset($classesInfo['classMethods']['protectedMethods']))
            foreach ($classesInfo['classMethods']['protectedMethods'] as $value) {
                if ($value != null) {
                    $output .= $this->createMethodRow($classesInfo, $value, $file);
                }
            }

        return $output;
    }

    /**
     * @param $classesInfo
     * @return string
     */
    public function createMiddlewareMenu($classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/middlewareMenu.hctpl');
        $output = '';

        foreach ($classesInfo['middleware'] as $value) {
            $field = str_replace('{className}', $value['classInfo']['className'], $file);
            $output .= $field;
        }

        return $output;
    }

    /**
     * @param $classesInfo
     * @return string
     */
    public function createMiddlewareRow($classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/middlewareRow.hctpl');
        $output = '';
        if (isset($classesInfo['middleware'])) {
            foreach ($classesInfo['middleware'] as $value) {
                $field = str_replace('{packageName}', $value['classInfo']['name'], $file);
                $field = str_replace('{className}', $value['classInfo']['className'], $field);
                $field = str_replace('{classInheritance}', implode(' &#8594 ', $value['classInheritance']), $field);
                $field = str_replace('{methods}', $this->middlewarePublicMethods($value), $field);
                $field = str_replace('{protectedMethods}', $this->middlewareProtectedMethods($value), $field);
                $field = str_replace('{privateMethods}', $this->middlewarePrivateMethods($value), $field);


                $output .= $field;
            }

        }
        return $output;
    }

    /**
     * @param $classesInfo
     * @return string
     */
    public function middlewarePublicMethods($classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/middlewareMethod.hctpl');
        $output = '';

        if (isset($classesInfo['classMethods']['publicMethods']))
            foreach ($classesInfo['classMethods']['publicMethods'] as $value) {
                if ($value != null) {
                    $output .= $this->createMethodRow($classesInfo, $value, $file);
                }
            }

        return $output;
    }

    /**
     * @param $classesInfo
     * @return string
     */
    public function middlewarePrivateMethods($classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/middlewareMethodPrivate.hctpl');
        $output = '';

        if (isset($classesInfo['classMethods']['privateMethods']))
            foreach ($classesInfo['classMethods']['privateMethods'] as $value) {
                if ($value != null) {
                    $output .= $this->createMethodRow($classesInfo, $value, $file);
                }
            }
        return $output;
    }

    /**
     * @param $classesInfo
     * @return string
     */
    public function middlewareProtectedMethods($classesInfo)
    {

        $file = $this->file->get(__DIR__ . '/templates/docs/middlewareMethodProtected.hctpl');
        $output = '';

        if (isset($classesInfo['classMethods']['protectedMethods']))
            foreach ($classesInfo['classMethods']['protectedMethods'] as $value) {
                if ($value != null) {
                    $output .= $this->createMethodRow($classesInfo, $value, $file);
                }
            }
        return $output;
    }


    /**
     * @param $classesInfo
     * @return string
     */
    public function createCommandsRow($classesInfo)
    {

        $file = $this->file->get(__DIR__ . '/templates/docs/commandsRow.hctpl');
        $output = '';


        foreach ($classesInfo['commands'] as $value) {
            $field = str_replace('{packageName}', $value['classInfo']['name'], $file);
            $field = str_replace('{className}', $value['classInfo']['className'], $field);
            $field = str_replace('{classInheritance}', implode(' &#8594 ', $value['classInheritance']), $field);
            $field = str_replace('{commandName}', $value['commandsInfo']['signature'], $field);
            $field = str_replace('{commandDescription}', $value['commandsInfo']['description'], $field);

            $output .= $field;
        }

        return $output;
    }

    /**
     * @param $classesInfo
     * @return string
     */
    public function createCommandsMenu($classesInfo)
    {
        $file = $this->file->get(__DIR__ . '/templates/docs/commandsMenu.hctpl');
        $output = '';

        foreach ($classesInfo['commands'] as $value) {
            $field = str_replace('{className}', $value['classInfo']['className'], $file);
            $output .= $field;
        }

        return $output;
    }

    /**
     * Create doc file
     *
     * @param $classesInfo
     * @param $path
     */
    public function createDocFile($classesInfo, $path)
    {
        $composer = $this->file->get($path . 'composer.json');

        if (isset($classesInfo['middleware'])) {
            $this->createFileFromTemplate([
                "destination"         => base_path('public/docs/' . substr_replace(explode('/', explode(':', explode(',', $composer)[0])[1])[1], '', -1) . '.html'),
                "templateDestination" => __DIR__ . '/templates/docs/docs.hctpl',
                "content"             => [
                    "packageName"     => explode(':', explode(',', $composer)[0])[1],
                    "name"            => $classesInfo['commands'][0]['classInfo']['name'],
                    "commands"        => $this->createCommandsRow($classesInfo),
                    "commandsMenu"    => $this->createCommandsMenu($classesInfo),
                    "middleware"      => $this->createMiddlewareRow($classesInfo),
                    "middlewareMenu"  => $this->createMiddlewareMenu($classesInfo),
                    "controllers"     => $this->createControllerRow($classesInfo),
                    "controllersMenu" => $this->createControllerMenu($classesInfo)
                ],
            ]);
        } elseif (isset($classesInfo['commands'])) {
            $this->createFileFromTemplate([
                "destination"         => base_path('public/docs/' . substr_replace(explode('/', explode(':', explode(',', $composer)[0])[1])[1], '', -1) . '.html'),
                "templateDestination" => __DIR__ . '/templates/docs/docs.hctpl',
                "content"             => [
                    "packageName"     => explode(':', explode(',', $composer)[0])[1],
                    "name"            => $classesInfo['commands'][0]['classInfo']['name'],
                    "commands"        => $this->createCommandsRow($classesInfo),
                    "commandsMenu"    => $this->createCommandsMenu($classesInfo),
                    "controllers"     => $this->createControllerRow($classesInfo),
                    "controllersMenu" => $this->createControllerMenu($classesInfo),
                    "middleware"      => '',
                    "middlewareMenu"  => ''
                ],
            ]);
        } else {
            $this->createFileFromTemplate([
                "destination"         => base_path('public/docs/' . substr_replace(explode('/', explode(':', explode(',', $composer)[0])[1])[1], '', -1) . '.html'),
                "templateDestination" => __DIR__ . '/templates/docs/docs.hctpl',
                "content"             => [
                    "packageName"     => explode(':', explode(',', $composer)[0])[1],
                    "name"            => substr_replace(explode('/', explode(':', explode(',', $composer)[0])[1])[1], '', -1),
                    "commands"        => '',
                    "commandsMenu"    => '',
                    "controllers"     => $this->createControllerRow($classesInfo),
                    "controllersMenu" => $this->createControllerMenu($classesInfo),
                    "middleware"      => '',
                    "middlewareMenu"  => ''
                ],
            ]);
        }
    }

    /**
     * @return string
     * @internal param $classesInfo
     */
    public function createPackageRow()
    {

        $files = $this->getHtmlFiles(base_path('public/docs/'));

        $file = $this->file->get(__DIR__ . '/templates/docs/packageRow.hctpl');
        $output = '';

        foreach ($files as $value) {
            $field = str_replace('{packageName}', strtoupper(substr($value->getFilename(), 0, -5)), $file);
            $field = str_replace('{link}', $value->getFilename(), $field);

            $output .= $field;
        }

        return $output;
    }

    public function createIndexFile()
    {
        $this->createFileFromTemplate([
            "destination"         => base_path('public/docs/index.html'),
            "templateDestination" => __DIR__ . '/templates/docs/index.hctpl',
            "content"             => [
                "packages" => $this->createPackageRow(),
            ],
        ]);
    }

    /**
     * Create website frame
     *
     * @internal param $path
     */
    public function createWebsiteFrame()
    {
        $fileList = [
            //assets/css
            [
                "destination"         => base_path('public/docs/assets/css/styles.css'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/css/styles.hctpl',
            ],
            //assets/js
            [
                "destination"         => base_path('public/docs/assets/js/main.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/js/main.hctpl',
            ],
            //assets/less
            [
                "destination"         => base_path('public/docs/assets/less/base.less'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/less/base.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/less/doc.less'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/less/doc.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/less/landing.less'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/less/landing.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/less/mixins.less'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/less/mixins.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/less/styles.less'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/less/styles.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/less/theme-default.less'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/less/theme-default.hctpl',
            ],
            //assets/plugins
            [
                "destination"         => base_path('public/docs/assets/plugins/jquery-1.12.3.min.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/jquery-1123-min.hctpl',
            ],
            //assets/plugins/bootstrap/css
            [
                "destination"         => base_path('public/docs/assets/plugins/bootstrap/css/bootstrap.css'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/bootstrap/css/bootstrap.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/plugins/bootstrap/css/bootstrap.min.css'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/bootstrap/css/bootstrap-min.hctpl',
            ],
            //assets/plugins/bootstrap/js
            [
                "destination"         => base_path('public/docs/assets/plugins/bootstrap/js/bootstrap.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/bootstrap/js/bootstrap.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/plugins/bootstrap/js/bootstrap.min.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/bootstrap/js/bootstrap-min.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/plugins/bootstrap/js/npm.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/bootstrap/js/npm.hctpl',
            ],
            //assets/plugins/prism
            [
                "destination"         => base_path('public/docs/assets/plugins/prism/prism.css'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/prism/prism-css.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/plugins/prism/prism.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/prism/prism-js.hctpl',
            ],
            //assets/plugins/prism/min
            [
                "destination"         => base_path('public/docs/assets/plugins/prism/min/prism-min.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/prism/min/prism-min.hctpl',
            ],
            //assets/plugins/jquery-scrollTo
            [
                "destination"         => base_path('public/docs/assets/plugins/jquery-scrollTo/jquery.scrollTo.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/jquery-scrollTo/jquery.scrollTo.hctpl',
            ],
            [
                "destination"         => base_path('public/docs/assets/plugins/jquery-scrollTo/jquery.scrollTo.min.js'),
                "templateDestination" => __DIR__ . '/templates/docs/assets/plugins/jquery-scrollTo/jquery.scrollTo.min.hctpl',
            ],


        ];

        foreach ($fileList as $value)
            if (!file_exists($value['destination']))
                $this->createFileFromTemplate($value);

    }

    /**
     * You will need to install 'symfony/finder' package
     * URL: http://symfony.com/doc/current/components/finder.html
     *
     * Gets all php files in provided directory
     *
     * @param $directory
     * @return array
     */
    public function getPhpFiles($directory)
    {
        $finder = new Finder();
        $finder->files()->in($directory);
        $files = [];
        foreach ($finder as $file) {
            if ($file->getExtension() == 'php')
                $files[] = $file;
        }

        return $files;
    }

    public function getHtmlFiles($directory)
    {
        $finder = new Finder();
        $finder->files()->in($directory);
        $files = [];
        foreach ($finder as $file) {
            if ($file->getExtension() == 'html' && $file->getFilename() != 'index.html')
                $files[] = $file;
        }

        return $files;
    }

    public function getCommands(ReflectionClass $parsedClass)
    {
        $commandInfo = [
            'signature'   => $parsedClass->getDefaultProperties()['signature'],
            'description' => $parsedClass->getDefaultProperties()['description']
        ];

        return $commandInfo;
    }

    /**
     * Get class basic info
     *
     * @param $parsedClass
     * @return array
     */
    public function getClassInfo(ReflectionClass $parsedClass)
    {
        if ($parsedClass->name != null) {
            // Class info
            $name = explode("\\", $parsedClass->name)[1];                                                               // returns package name
            $gitHub = '';                                                                                               // returns git hub url
            $installName = explode("\\", $parsedClass->getName())[0] . '/' . explode("\\", $parsedClass->getName())[1];  // returns install name
            $serviceProvider = '';                                                                                      // returns service provider
            $className = $parsedClass->getShortName();                                                                  // returns class name

            return $info = ([
                'name'            => $name,
                'gitHub'          => $gitHub,
                'installName'     => $installName,
                'serviceProvider' => $serviceProvider,
                'className'       => $className
            ]);
        }
    }

    /**
     * Gets class type
     *
     * @param $parsedClass
     * @return null|string
     */
    public function getClassType($parsedClass)
    {
        $classType = null;

        if ($parsedClass->isAbstract())                                                                              // checks if class is abstract
            $classType = 'abstract';
        elseif ($parsedClass->isFinal())                                                                             // checks if class is final
            $classType = 'final';

        return $classType;
    }

    /**
     * Gets class inheritance
     *
     * @param $parsedClass
     * @return array
     */
    public function getClassInheritance(ReflectionClass $parsedClass)
    {
        $className = $parsedClass->getShortName();                                                                  // returns class name

        $inheritance[] = $className;

        $parentClass = $parsedClass->getParentClass();
        while ($parentClass)                                                                                         // while parent class is true
        {
            $inheritance[] = $parentClass->getShortName();                                                          // add parent class name to the inheritance array
            $parentClass = $parentClass->getParentClass();                                                          // get next parent class
        }

        return $inheritance;
    }

    /**
     * Gets class properties
     *
     * @param $parsedClass
     * @return array
     */
    public function getClassProperties(ReflectionClass $parsedClass)
    {
        $properties = null;

        foreach ($parsedClass->getProperties() as $property) {
            if ($parsedClass->getName() == $property->class)                                // check only for the required class
            {
                if ($property->isPrivate())                                                                         // check whether class is private
                    $type = 'private';
                elseif ($property->isPublic())                                                                      // check whether class is public
                    $type = 'public';
                elseif ($property->isProtected())                                                                   // check whether class is protected
                    $type = 'protected';

                $property = [
                    'className'    => $property->getDeclaringClass()->getName(),
                    'propertyName' => $property->getName(),
                    'type'         => $type,
                    'declaredBy'   => $property->getDeclaringClass()->getShortName(),
                    'comment'      => str_replace(['     ', '/**', '*/', '* ', '*', "/\r|\n/"], '', $property->getDocComment())
                ];
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * Gets class methods
     *
     * @param $parsedClass
     * @return array
     */
    public function getClassMethods(ReflectionClass $parsedClass)
    {
        $methods = [];

        foreach ($parsedClass->getMethods() as $method) {
            if ($parsedClass->getName() == $method->class) {
                switch ($method->getModifiers()) {
                    case ReflectionMethod::IS_PUBLIC:

                        $methods['publicMethods'][] = $this->organizeMethod($method);
                        break;

                    case ReflectionMethod::IS_PRIVATE:

                        $methods['privateMethods'][] = $this->organizeMethod($method);
                        break;

                    case ReflectionMethod::IS_PROTECTED:

                        $methods['protectedMethods'][] = $this->organizeMethod($method);
                        break;
                }
            }

        }
        return $methods;
    }

    /**
     * get public methods
     *
     * @param $method
     * @return array
     */
    private function organizeMethod($method)
    {
        $filterDocComment = str_replace(['     ', '/**', '* ', "*/", "\r\n"], '', $method->getDocComment());
        $filterComment = explode("@", $filterDocComment, 2);
        $comment = str_replace('*', '', $filterComment[0]);
        $filterResults = null;

        if (count($filterComment) > 1)
            $filterResults = "@" . $filterComment[1];
        $filterParameters = array_filter(explode("@", $filterResults));

        $post_data = [
            'method'  => $method->name,
            'comment' => $comment,
            'param'   => [],
            'throws'  => [],
            'return'  => []
        ];

        foreach ($filterParameters as $values) {
            $value = explode(' ', trim($values));

            if (!isset($value[1]))
                $value[1] = 'null';

            switch ($value[0]) {
                case 'param' :

                    $post_data['param'][] = $value[1];
                    break;

                case 'return' :

                    $post_data['return'][] = $value[1];
                    break;

                case 'throws' :

                    $post_data['throws'][] = $value[1];
                    break;
            }
        }

        return $post_data;
    }

}