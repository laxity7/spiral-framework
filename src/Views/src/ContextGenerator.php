<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Views;

use Spiral\Views\Context\ValueDependency;

/**
 * ContextGenerator creates all possible variations of context values. Use this class
 * to properly warm up views cache.
 */
final class ContextGenerator
{
    /** @var ContextInterface */
    private $context;

    /**
     * @param ContextInterface $context
     */
    public function __construct(ContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * Generate all possible context variations.
     *
     * @return ContextInterface[]
     */
    public function generate(): array
    {
        $dependencies = $this->context->getDependencies();

        return $this->rotate(new ViewContext(), $dependencies);
    }

    /**
     * Rotate all possible context values using recursive tree walk.
     *
     * @param ContextInterface      $context
     * @param DependencyInterface[] $dependencies
     *
     * @return ContextInterface[]
     */
    private function rotate(ContextInterface $context, array $dependencies): array
    {
        if (empty($dependencies)) {
            return [];
        }

        $top = array_shift($dependencies);

        $variants = [];
        foreach ($top->getVariants() as $value) {
            $variant = $context->withDependency(new ValueDependency($top->getName(), $value));

            if (empty($dependencies)) {
                $variants[] = $variant;
                continue;
            }

            foreach ($this->rotate($variant, $dependencies) as $inner) {
                $variants[] = $inner;
            }
        }

        return $variants;
    }
}
