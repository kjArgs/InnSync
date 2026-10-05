<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends GeneratorCommand
{
    protected $signature = 'make:repository {name : The name of the repository}';
    protected $description = 'Create a new repository class';
    protected $type = 'Repository';

    protected function getStub()
    {
        return base_path('stubs/repository.stub');
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Repositories';
    }

    /**
     * Replace the model placeholders in the stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        // Extracts the base name (e.g., converts "UserRepository" to "User")
        $pureName = str_replace($this->type, '', class_basename($name));
        $modelName = Str::studly($pureName);

        return str_replace('{{ model }}', $modelName, $stub);
    }
}
