<?php


namespace Crosf32\ControllerRefactorer\Command;


use Crosf32\ControllerRefactorer\Helper\ExtendedReflectionClass;
use ReflectionAttribute;
use ReflectionMethod;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:refactor-controller',
    description: 'Decouple multiple routes in single controllers to multiple controller classes',
    hidden: false,
)]
class ControllerRefactorerCommand extends Command
{
    private string $filePath;
    private string $extends;

    public function __construct(private KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filePath', InputArgument::REQUIRED, 'Controller file path')
            ->addArgument('extends', InputArgument::OPTIONAL, 'class name extends');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->filePath = $input->getArgument('filePath');
        $this->extends = $input->getArgument('extends') ?? 'AbstractController';

        include str_replace('\\', '/', $this->kernel->getProjectDir() . '/src/Http/'.$this->filePath);

        $refClass = new ExtendedReflectionClass($this->getFullNamespace($this->filePath));

        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes();
            if (count($attributes) > 0 && "Symfony\Component\Routing\Annotation\Route" === $attributes[0]->getName()) {
                $this->writeNewController($attributes[0], $refClass, $method);
            }
        }

        return Command::SUCCESS;
    }

    private function writeNewController(
        ReflectionAttribute $attr,
        ExtendedReflectionClass $targetClass,
        ReflectionMethod $method,
    ) {
        [$className, $directory, $nameSpace] = $this->getNecessaryVariables($targetClass);

        $useStatements = $targetClass->getUseStatements();
        $baseRouteAnnotations = $this->getBaseRoute($targetClass);

        $routeAnnotation = '#[Route('.$this->convertAttributeToString($attr, $baseRouteAnnotations) . ')]';
        $methodCode = str_replace($method->getName().'(', '__invoke(', $this->getBody($this->kernel->getProjectDir() . '/src/Http/' . $this->filePath, $method));

        $newClassName = ucfirst($method->getName()).$className;

        file_put_contents($directory.'/'.$newClassName, '<?php' . PHP_EOL . PHP_EOL . 'namespace ' . $nameSpace . ';'.PHP_EOL.PHP_EOL.
            join(PHP_EOL, $useStatements).PHP_EOL.PHP_EOL.
            $routeAnnotation.PHP_EOL.
            'class '. substr($newClassName, 0, -4) . ' extends ' . $this->extends . ' {'.PHP_EOL.
            $method->getDocComment() . PHP_EOL .
            $methodCode.PHP_EOL.
            '}');
    }

    private function getNecessaryVariables(ExtendedReflectionClass $reflectionClass)
    {
        $values = explode('/', str_replace('\\', '/', $reflectionClass->getFileName()));
        $className = array_pop($values);

        $nameSpaces = explode('\\', (string) $this->getFullNamespace($this->filePath));
        array_pop($nameSpaces);
        $nameSpace = join('\\', $nameSpaces);

        $directory = join('/', $values);

        return [$className, $directory, $nameSpace];
    }

    private function convertAttributeToString(ReflectionAttribute $attr, ?array $baseRouteAnnotations): string
    {
        $args = [];

        foreach ($attr->getArguments() as $name => $arg) {
            $val = (is_array($arg) ? json_encode($arg) : $arg);

            if (!is_null($baseRouteAnnotations) && count($baseRouteAnnotations) >= 2) {
                if (is_int($name)) {
                    $val = '"' . $baseRouteAnnotations[0] . $val . '"';
                } elseif ($name === "name") {
                    $val = '"' . $baseRouteAnnotations[1] . $val . '"';
                }
            }

            $args[] = (!is_int($name) ? $name . ': ' : '') . $val;
        }

        return join(', ', $args);
    }

    private function getBody(string $filename, ReflectionMethod $func): string
    {
        $start_line = $func->getStartLine() - 1;
        $end_line = $func->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);

        return implode('', array_slice($source, $start_line, $length));
    }

    private function getBaseRoute(ExtendedReflectionClass $refClass): ?array
    {
        foreach ($refClass->getAttributes() as $attr) {
            if ($attr->getName() === "Symfony\Component\Routing\Annotation\Route") {
                return [$attr->getArguments()[0], $attr->getArguments()['name']];
            }
        }

        return null;
    }

    private function getFullNamespace(string $filePath): string
    {
        return 'App\Http\\'.substr(str_replace('/', '\\', $filePath), 0, -4);
    }
}
