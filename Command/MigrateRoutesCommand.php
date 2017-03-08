<?php

namespace Stoakes\RoutingConverterBundle\Command;

use Gnugat\Redaktilo\Editor;
use Gnugat\Redaktilo\EditorFactory;
use Gnugat\Redaktilo\Text;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Route;

class MigrateRoutesCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     * php bin/console stoakes:convert_yml ./src/Mgate/DashboardBundle/Resources/config/routing.yml /suivi
     */
    protected function configure()
    {
        $this
            ->setName('stoakes:convert_yml')
            ->addArgument('resource', InputArgument::REQUIRED, 'The routing.yml file to convert')
            ->addArgument('prefix', InputArgument::OPTIONAL, 'The prefix for the created routes')
            ->setDescription('Hello PhpStorm');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileLoader = new FileLocator(__DIR__ . '/../../../../');
        $loader = new ConvertRouteLoader($fileLoader);
        $routes = $loader->load($input->getArgument('resource'));

        foreach ($routes->all() as $name => $route) {
            $string = ' * @Route(name="' . $name . '", path="' . $input->getArgument('prefix') . '' . $route->getPath() . '"';

            $string = $this->methodsHandler($route, $string);
            $string = $this->requirementsHandler($route, $string);
            $string = $this->defaultsHandler($route, $string);

            $string .= ')';
            echo $string . "\n";

            $controller = $this->getController($route);
            $classname = str_replace('\\', '/', get_class($controller[0]));
            $actionText = $controller[1];

            $editor = EditorFactory::createEditor();
            $text = $editor->open('src/' . $classname . '.php');

            if ($editor->hasBelow($text, '/' . $actionText . '/')) {

                //go to the method declaration line
                $editor->jumpBelow($text, '/' . $actionText . '/');
                $lineAbove = $text->getLine($text->getCurrentLineNumber() - 1);
                $indent = strlen($text->getLine()) - strlen(ltrim($text->getLine()));
                $indentation = str_repeat(' ', $indent);

                //is the line above a full commentary ? ie /** hello */
                if (preg_match('#/\*\*(.*)\*/#', $lineAbove)) {
                    echo 'full';
                } elseif (preg_match('#\*/#', $lineAbove)) { //is the line above an ending commentary ie contains */
                    $editor->insertAbove($text, $indentation . '' . $string, $text->getCurrentLineNumber() - 1);
                } elseif (preg_match('/^ *$/', $lineAbove) || strlen(trim($lineAbove)) == 0) { //is the line above an empty line ?
                    $editor->insertAbove($text, $indentation . ' */', $text->getCurrentLineNumber());
                    $editor->insertAbove($text, $indentation . '' . $string, $text->getCurrentLineNumber());
                    $editor->insertAbove($text, $indentation . '/**', $text->getCurrentLineNumber());
                } else { //content but not annotations
                    $editor->insertAbove($text, $indentation . ' */', $text->getCurrentLineNumber());
                    $editor->insertAbove($text, $indentation . '' . $string, $text->getCurrentLineNumber());
                    $editor->insertAbove($text, $indentation . '/**', $text->getCurrentLineNumber());
                }
                //handle route annotation import
                $text = $this->importHandler($editor, $text);
                $editor->save($text);

            } else {
                throw new \RuntimeException('unable to find ' . $actionText . ' in ' . $classname);
            }


        }


    }


    private function methodsHandler(Route $route, $string)
    {
        if ($route->getMethods()) {
            $string .= ', methods={';
            $i = 0;
            foreach ($route->getMethods() as $method) {
                if ($i != 0) {
                    $string .= ',';
                }
                $string .= '"' . $method . '"';
                $i++;
            }
            $string .= '}';

        }
        return $string;
    }

    private function requirementsHandler(Route $route, $string)
    {
        if ($route->getRequirements()) {
            $string .= ', requirements={';
            $i = 0;
            foreach ($route->getRequirements() as $key => $value) {
                if ($i != 0) {
                    $string .= ', ';
                }
                $string .= '"' . $key . '": "' . $value . '"';
                $i++;
            }
            $string .= '}';
        }
        return $string;
    }

    private function defaultsHandler(Route $route, $string)
    {
        if (count($route->getDefaults()) > 1) { // there always are a _controller field.
            $string .= ', defaults={';

            $i = 0;
            foreach ($route->getDefaults() as $key => $value) {
                if ($key != '_controller') {
                    if ($i != 0) {
                        $string .= ', ';
                    }
                    $string .= '"' . $key . '": "' . $value . '"';

                    $i++;
                }
            }
            $string .= '}';
        }
        return $string;
    }

    private function getController(Route $route)
    {
        $controller = $route->getDefault('_controller');
        $req = new Request([], [], array('_controller' => $controller));
        $controllerResolver = $this->getContainer()->get('debug.controller_resolver');
        $controller = $controllerResolver->getController($req);

        return $controller;
    }

    private function importHandler(Editor $editor, Text $text)
    {
        if (!$editor->hasBelow($text, 'use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;', 0)) {
            $editor->jumpAbove($text, '#use S#'); //jump above first import. There are at least the controller import.
            $editor->insertAbove($text, 'use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;');
        }

        return $text;
    }

}
