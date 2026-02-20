<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\PHPStan\Rules\Classes;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final readonly class ForbidAttributeRule implements Rule
{
    /**
     * @param list<class-string<\Attribute>> $forbiddenAttributes
     */
    public function __construct(
        private array $forbiddenAttributes,
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check class attributes
        if ($node instanceof Class_) {
            foreach ($node->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isForbiddenAttribute($attr, $scope)) {
                        $errors[] = RuleErrorBuilder::message(
                            sprintf(
                                'Usage of #[%s] is forbidden on classes.',
                                $attr->name->toString(),
                            ),
                        )->identifier('attribute.forbidden.classScope')->build();
                    }
                }
            }
        }

        // Check method attributes
        if ($node instanceof ClassMethod) {
            foreach ($node->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isForbiddenAttribute($attr, $scope)) {
                        $errors[] = RuleErrorBuilder::message(
                            sprintf(
                                'Usage of #[%s] is forbidden on class methods.',
                                $attr->name->toString(),
                            ),
                        )->identifier('attributes.forbidden.methodScope')->build();
                    }
                }
            }
        }

        return $errors;
    }

    private function isForbiddenAttribute(Attribute $attribute, Scope $scope): bool
    {
        $resolvedName = $scope->resolveName($attribute->name);

        return in_array($resolvedName, $this->forbiddenAttributes, true);
    }
}
