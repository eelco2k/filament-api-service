<?php

namespace Rupadana\ApiService\Commands;

use Exception;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\text;

class MakeApiDocsCommand extends Command
{
    use CanManipulateFiles;
    protected $description = 'Create a new API Swagger Docs Resource for supporting filamentphp Resource';
    protected $signature = 'make:filament-api-docs {resource?} {namespace?}';

    public function handle(): int
    {

        $baseServerPath = app_path('Virtual/');
        $serverNameSpace = 'App\\Virtual';
        $serverFile = 'ApiDocsController.php';

        $resourcePath = $baseServerPath . 'Filament/Resources';
        $resourceNameSpace = $serverNameSpace . '\\Filament\\Resources';

        $isNotInstalled = $this->checkForCollision([$baseServerPath . '/' . $serverFile]);

        // ApiDocsController exists?
        if (!$isNotInstalled) {

            $this->components->info("Please provide basic API Docs information.");
            $this->components->info("All API Docs Resources will be placed in the app Virtual folder.");

            $serverTitle = text(
                label: 'Give the API Docs Server a name...',
                placeholder: 'API Documentation',
                default: 'API Documentation',
                required: true,
            );

            $serverVersion = text(
                label: 'Starting version of API Docs...',
                placeholder: '0.1',
                default: '0.1',
                required: true,
            );

            $serverContactName = text(
                label: 'API Contact Name',
                placeholder: 'your name',
                default: '',
                required: false,
            );

            $serverContactEmail = text(
                label: 'API Contact E-mail',
                placeholder: 'your@email.com',
                default: '',
                required: false,
            );

            $serverTerms = text(
                label: 'API Terms of Service url',
                placeholder: config('app.url') . '/terms-of-service',
                default: '',
            );

            $this->createDirectory('Virtual/Filament/Resources');

            $this->copyStubToApp('ApiDocsController', $baseServerPath . '/' . $serverFile, [
                'namespace' => $serverNameSpace,
                'title' => $serverTitle,
                'version' => $serverVersion,
                'contactName' => $serverContactName,
                'contactEmail' => $serverContactEmail,
                'terms' => $serverTerms,
                'licenseName' => 'MIT License',
                'licenseUrl' => 'https://opensource.org/license/mit',
            ]);
        }

        $model = (string) str($this->argument('resource') ?? text(
            label: 'What is the Resource name?',
            placeholder: 'Blog',
            required: true,
        ))
            ->studly()
            ->beforeLast('Resource')
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\');

        if (blank($model)) {
            $model = 'Resource';
        }

        if (!str($model)->contains('\\')) {
            $namespace = (string) str($this->argument('namespace') ?? text(
                label: 'What is the namespace of this Resource?',
                placeholder: 'App\Filament\Resources',
                default: 'App\Filament\Resources',
                required: true,
            ))
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->studly()
                ->replace('/', '\\');

            if (blank($namespace)) {
                $namespace = 'App\\Filament\\Resources';
            }
        }

        $modelClass = (string) str($model)->afterLast('\\');

        $modelNamespace = str($model)->contains('\\') ?
            (string) str($model)->beforeLast('\\') :
            $namespace;

        $pluralModelClass = (string) str($modelClass)->pluralStudly();

        $resource = "{$modelNamespace}\\{$modelClass}Resource";
        $resourceClass = "{$modelClass}Resource";

        $resourcePath = (string) str($modelNamespace)->replace('\\', '/')->replace('//', '/');
        $baseResourceSourcePath =  (string) str($resourceClass)->prepend('/')->prepend(base_path($resourcePath))->replace('\\', '/')->replace('//', '/')->replace('App', 'app');

        $handlerSourceDirectory = "{$baseResourceSourcePath}/Api/Handlers/";
        $transformersSourceDirectory = "{$baseResourceSourcePath}/Api/Transformers/";

        $namespace = text(
            label: 'In which namespace would you like to create this API Docs Resource in?',
            default: $resourceNameSpace
        );

        $handlersVirtualNamespace = "{$namespace}\\{$resourceClass}\\Handlers";
        $transformersVirtualNamespace = "{$namespace}\\{$resourceClass}\\Transformers";

        $baseResourceVirtualPath = (string) str($resourceClass)->prepend('/')->prepend($resourcePath)->replace('\\', '/')->replace('//', '/');

        $handlerVirtualDirectory = "{$baseResourceVirtualPath}/Handlers/";
        $transformersVirtualDirectory = "{$baseResourceVirtualPath}/Transformers/";

        if (method_exists($resource, 'getApiTransformer')) {
            // generate API transformer
            $transformer = ($modelNamespace . "\\" . $resourceClass)::getApiTransformer();
            $transformerClassPath = (string) str($transformer);
            $transformerClass = (string) str($transformerClassPath)->afterLast('\\');

            $stubVars = [
                'namespace' => $namespace,
                'modelClass' => $pluralModelClass,
                'resourceClass' => $resourceClass,
                'transformerName'   => $transformerClass,
            ];

            if (!$this->checkForCollision(["{$transformersVirtualDirectory}/{$transformerClass}.php"])) {
                $this->copyStubToApp("Api/Transformer", $transformersVirtualDirectory . '/' . $transformerClass . '.php', $stubVars);
            }
        }

        $resourceTransformers = [];
        if (method_exists($resource, 'apiTransformers')) {
            $transformers = ($modelNamespace . "\\" . $resourceClass)::apiTransformers();
            foreach($transformers as $transformer) {
                $resourceTransformers[] = str($transformer)->afterLast('\\')->kebab();
            }
        }

        try {
            $handlerMethods = File::allFiles($handlerSourceDirectory);

            foreach ($handlerMethods as $handler) {
                $handlerName = basename($handler->getFileName(), '.php');
                // stub exists?
                if (!$this->fileExists($this->getDefaultStubPath() . "/ApiDocs{$handlerName}")) {

                    if (!$this->checkForCollision(["{$handlerVirtualDirectory}/{$handlerName}.php"])) {

                        $this->copyStubToApp("Api/{$handlerName}", $handlerVirtualDirectory . '/' . $handlerName . '.php', [
                            'handlersVirtualNamespace' => $handlersVirtualNamespace,
                            'transformersVirtualNamespace' => $transformersVirtualNamespace,
                            'resource' => $resource,
                            'resourceClass' => $resourceClass,
                            'realResource' => "{$resource}\\Api\\Handlers\\{$handlerName}",
                            'resourceNamespace' => $modelNamespace,
                            'modelClass' => $modelClass,
                            'pluralClass' => $pluralModelClass,
                            'handlerClass' => $handler,
                            'handlerName' => $handlerName,
                            'capitalsResource' => strtoupper($modelClass),
                            'resourceTransformers' => $resourceTransformers,
                            'path' => '/' . str($pluralModelClass)->kebab(),
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            $this->components->error($e->getMessage());
        }

        $this->components->info("Successfully created API Docs for {$resource}!");

        return static::SUCCESS;
    }

    private function createDirectory(string $path): void
    {
        $path = app_path($path);

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }
}
