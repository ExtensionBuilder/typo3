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

namespace TYPO3\CMS\Install\Tests\Unit\ExtensionScanner\Php\Matcher;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Install\ExtensionScanner\Php\GeneratorClassesResolver;
use TYPO3\CMS\Install\ExtensionScanner\Php\Matcher\ClassConstantMatcher;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ClassConstantMatcherTest extends UnitTestCase
{
    #[Test]
    public function hitsFromFixtureAreFound(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 2));
        $fixtureFile = __DIR__ . '/Fixtures/ClassConstantMatcherFixture.php';
        $statements = $parser->parse(file_get_contents($fixtureFile));

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new GeneratorClassesResolver());

        $configuration = [
            'TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_FE' => [
                'restFiles' => [
                    'Breaking-80700-DeprecatedFunctionalityRemoved.rst',
                ],
            ],
            'TYPO3\CMS\Core\Page\PageRenderer::JQUERY_NAMESPACE_DEFAULT' => [
                'restFiles' => [
                    'Breaking-82378-RemoveNamespacedJQuery.rst',
                ],
            ],
        ];
        $subject = new ClassConstantMatcher($configuration);
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);
        $expectedHitLineNumbers = [
            30,
            31,
            32,
            41,
        ];
        $actualHitLineNumbers = [];
        foreach ($subject->getMatches() as $hit) {
            $actualHitLineNumbers[] = $hit['line'];
        }
        self::assertEquals($expectedHitLineNumbers, $actualHitLineNumbers);
    }

    public static function matchesReturnsExpectedRestFilesDataProvider(): array
    {
        return [
            'a straight match' => [
                [
                    'Foo\Bar::aClassConstant' => [
                        'restFiles' => [
                            'Foo-1.rst',
                            'Foo-2.rst',
                        ],
                    ],
                ],
                '<?php
                $foo = \Foo\Bar::aClassConstant;',
                [
                    0 => [
                        'restFiles' => [
                            'Foo-1.rst',
                            'Foo-2.rst',
                        ],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('matchesReturnsExpectedRestFilesDataProvider')]
    #[Test]
    public function matchesReturnsExpectedRestFiles(array $configuration, string $phpCode, array $expected): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 2));
        $statements = $parser->parse($phpCode);

        $subject = new ClassConstantMatcher($configuration);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);

        $result = $subject->getMatches();
        self::assertSame($expected[0]['restFiles'], $result[0]['restFiles']);
    }
}
