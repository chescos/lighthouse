<?php

namespace Nuwave\Lighthouse\WhereHasConditions;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\WhereConstraints\Operator;
use Nuwave\Lighthouse\WhereConstraints\SQLOperator;

class WhereHasConditionsServiceProvider extends ServiceProvider
{
    const DEFAULT_WHERE_HAS_CONDITIONS = 'WhereHasConditions';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Operator::class, SQLOperator::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function boot(DirectiveFactory $directiveFactory, Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                /** @var \Nuwave\Lighthouse\WhereConstraints\Operator $operator */
                $operator = $this->app->make(Operator::class);

                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        static::createWhereHasConditionsInputType(
                            static::DEFAULT_WHERE_HAS_CONDITIONS,
                            'Dynamic relationship WHERE condition for queries.',
                            'String'
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::enumTypeDefinition(
                            $operator->enumDefinition()
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
                            scalar Mixed @scalar(class: "MLL\\\GraphQLScalars\\\Mixed")
                        ')
                    );
            }
        );
    }

    public static function createWhereHasConditionsInputType(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        /** @var \Nuwave\Lighthouse\WhereConstraints\Operator $operator */
        $operator = app(Operator::class);

        $operatorName = PartialParser
            ::enumTypeDefinition(
                $operator->enumDefinition()
            )
            ->name
            ->value;
        $operatorDefault = $operator->default();

        return PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "The column that is used for the condition."
                column: $columnType

                "The operator that is used for the condition."
                operator: $operatorName = $operatorDefault

                "The value that is used for the condition."
                value: Mixed

                "A set of conditions that requires all conditions to match."
                AND: [$name!]

                "A set of conditions that requires at least one condition to match."
                OR: [$name!]
            }
GRAPHQL
        );
    }
}
