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

namespace TYPO3\CMS\Core\Tests\Functional\TypoScript\IncludeTree;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\AtImportInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\ConditionElseInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\ConditionInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\ConditionStopInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\DefaultTypoScriptMagicKeyInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\FileInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\IncludeInterface;
use TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode\SegmentInclude;
use TYPO3\CMS\Core\TypoScript\IncludeTree\TreeFromLineStreamBuilder;
use TYPO3\CMS\Core\TypoScript\Tokenizer\Line\LineStream;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LosslessTokenizer;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LossyTokenizer;
use TYPO3\CMS\Core\TypoScript\Tokenizer\Token\Token;
use TYPO3\CMS\Core\TypoScript\Tokenizer\Token\TokenType;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TreeFromLineStreamBuilderTest extends FunctionalTestCase
{
    /**
     * @var bool Speed up this test case, it needs no database
     */
    protected bool $initializeDatabase = false;

    public function setUp(): void
    {
        parent::setUp();
        // Register custom comparator to compare IncludeTree without looking at the path:
        // They are important for the processing, but clutter the tests a lot. When
        // all include objects exists, that's enough for the tests to know if the include tree
        // has been calculated correctly.
        $this->registerComparator(new IncludeTreeObjectIgnoringIdentifierAndPathComparator());
    }

    public static function buildTreeConditionDataProvider(): iterable
    {
        $typoScript = '[foo = bar]';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LosslessTokenizer())->tokenize($typoScript));
        $expectedTree->addChild($subNode);
        yield 'condition without body' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = '[END]';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream($typoScriptLineStream);
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        yield 'condition end not in condition context adds condition stop include' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = '[GLOBAL]';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream($typoScriptLineStream);
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        yield 'condition global not in condition context adds condition stop include' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = "[GLOBAL]\n" .
            "foo = fooValue\n" .
            "[END]\n" .
            "bar = barValue\n" .
            "[GLOBAL]\n" .
            "[END]\n";
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $typoScriptLineStreamArray = iterator_to_array($typoScriptLineStream->getNextLine());
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[0]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        $subNode = new SegmentInclude();
        $subNode->setName('foo');
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[1]));
        $expectedTree->addChild($subNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[2]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        $subNode = new SegmentInclude();
        $subNode->setName('foo');
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[3]));
        $expectedTree->addChild($subNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[4]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[5]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        yield 'condition global and end not in condition context split' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = "[foo = bar]\n" .
            'foo';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $typoScriptLineStreamArray = iterator_to_array($typoScriptLineStream->getNextLine());
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[0]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[1]));
        $subNode->addChild($subSubNode);
        yield 'condition with body without end or global' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = "[foo = bar]\n" .
            "foo = fooValue\n" .
            "[END]\n" .
            'bar = barValue';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $typoScriptLineStreamArray = iterator_to_array($typoScriptLineStream->getNextLine());
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[0]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[1]));
        $subNode->addChild($subSubNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[2]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        $subNode = new SegmentInclude();
        $subNode->setName('foo');
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[3]));
        $expectedTree->addChild($subNode);
        yield 'condition with body with end' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = "[foo = bar]\n" .
            "foo\n" .
            "[GLOBAL]\n" .
            'bar';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $typoScriptLineStreamArray = iterator_to_array($typoScriptLineStream->getNextLine());
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[0]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[1]));
        $subNode->addChild($subSubNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[2]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        $subNode = new SegmentInclude();
        $subNode->setName('foo');
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[3]));
        $expectedTree->addChild($subNode);
        yield 'condition with body with global' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = "[foo = bar]\n" .
            "foo\n" .
            "[ELSE]\n" .
            "bar\n" .
            "[END]\n" .
            'baz';
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $typoScriptLineStreamArray = iterator_to_array($typoScriptLineStream->getNextLine());
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[0]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[1]));
        $subNode->addChild($subSubNode);
        $subNode = new ConditionElseInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[2]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[3]));
        $subNode->addChild($subSubNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[4]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        $subNode = new SegmentInclude();
        $subNode->setName('foo');
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[5]));
        $expectedTree->addChild($subNode);
        yield 'condition else with end' => [
            $tree,
            $expectedTree,
        ];

        $typoScript = "[foo = bar]\n" .
            "foo\n" .
            "[foo = baz]\n" .
            "bar\n" .
            "[END]\n";
        $typoScriptLineStream = (new LosslessTokenizer())->tokenize($typoScript);
        $typoScriptLineStreamArray = iterator_to_array($typoScriptLineStream->getNextLine());
        $tree = new FileInclude();
        $tree->setLineStream($typoScriptLineStream);
        $tree->setName('foo');
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($typoScriptLineStream);
        $expectedTree->setName('foo');
        $expectedTree->setSplit();
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = bar', 0, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[0]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[1]));
        $subNode->addChild($subSubNode);
        $subNode = new ConditionInclude();
        $subNode->setName('foo');
        $subNode->setConditionToken(new Token(TokenType::T_VALUE, 'foo = baz', 2, 1));
        $subNode->setSplit();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[2]));
        $expectedTree->addChild($subNode);
        $subSubNode = new SegmentInclude();
        $subSubNode->setName('foo');
        $subSubNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[3]));
        $subNode->addChild($subSubNode);
        $subNode = new ConditionStopInclude();
        $subNode->setLineStream((new LineStream())->append($typoScriptLineStreamArray[4]));
        $subNode->setName('foo');
        $expectedTree->addChild($subNode);
        yield 'second condition finishes first condition' => [
            $tree,
            $expectedTree,
        ];
    }

    #[DataProvider('buildTreeConditionDataProvider')]
    #[Test]
    public function buildTreeCondition(IncludeInterface $tree, IncludeInterface $expectedTree): void
    {
        $this->get(TreeFromLineStreamBuilder::class)->buildTree($tree, 'setup', new LossyTokenizer());
        self::assertEquals($expectedTree, $tree);
    }

    public static function buildTreeAtImportDataProvider(): iterable
    {
        $atImportStatement = '@import \'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript\'';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $expectedTree->addChild($subNode);
        yield 'atImport single file with ticks' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $expectedTree->addChild($subNode);
        yield 'atImport single file with doubleticks' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/notFoundFile.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        yield 'atImport with not found file' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/subInclude.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n"));
        $subNode->setOriginalLine($atImportLine);
        $subNode->setSplit();
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("SubDirectory.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        $expectedTree->addChild($subNode);
        yield 'atImport with directory' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/subInclude.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n"));
        $subNode->setOriginalLine($atImportLine);
        $subNode->setSplit();
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("SubDirectory.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        $expectedTree->addChild($subNode);
        yield 'atImport with directory and slash at end' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $expectedTree->addChild($subNode);
        yield 'atImport with single file adds .typoscript ending' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup*"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        yield 'atImport with setup* resolves setup.typoscript and setup2.typoscript' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/*typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/subInclude.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n"));
        $subNode->setOriginalLine($atImportLine);
        $subNode->setSplit();
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("SubDirectory.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        $expectedTree->addChild($subNode);
        yield 'atImport with *typoscript resolves setup.typoscript, setup2.typoscript and subInclude.typoscript' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/NotExistingDirectory/*.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        yield 'atImport with *.typoscript on not existing directory does not crash' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/*.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/subInclude.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n"));
        $subNode->setOriginalLine($atImportLine);
        $subNode->setSplit();
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("SubDirectory.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        $expectedTree->addChild($subNode);
        yield 'atImport with *.typoscript resolves setup.typoscript, setup2.typoscript and subInclude.typoscript' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup*.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $subNode->setOriginalLine($atImportLine);
        $expectedTree->addChild($subNode);
        yield 'atImport with setup*.typoscript resolves setup.typoscript and setup2.typoscript' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import \'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario2/pathTraversal1.typoscript\'';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario2/pathTraversal1.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario2/./subInclude.typoscript'\n"));
        $subNode->setSplit();
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario2/./subInclude.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("subInclude.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario2/./subInclude.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        $expectedTree->addChild($subNode);
        yield 'atImport with dot-slash path traversal is loaded' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import \'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario4/*.setup.typoscript\'';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $atImportLine = iterator_to_array($atImportLineStream->getNextLine())[0];
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario4/file1.setup.typoscript');
        $subNode->setOriginalLine($atImportLine);
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("file1\n"));
        $expectedTree->addChild($subNode);
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario4/file2.setup.typoscript');
        $subNode->setOriginalLine($atImportLine);
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("file2\n"));
        $expectedTree->addChild($subNode);
        yield 'atImport with EXT:.../SomeDirectory/*.setup.typoscript is allowed' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative1.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative1.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import './relativeTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/relativeTarget.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import './relativeTarget.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        yield 'atImport with dot slash relative include' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative2.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'relativeTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/relativeTarget.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'relativeTarget.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        yield 'atImport with relative include' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative3.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative3.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import './RelativeSubDirectory/relativeTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/RelativeSubDirectory/relativeTarget.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import './RelativeSubDirectory/relativeTarget.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        yield 'atImport with dot slash relative directory include' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative4.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative4.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'RelativeSubDirectory/relativeTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/RelativeSubDirectory/relativeTarget.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'RelativeSubDirectory/relativeTarget.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        yield 'atImport with relative directory include' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative5.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative5.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'RelativeRecursiveDirectory/relativeRecursive.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/RelativeRecursiveDirectory/relativeRecursive.typoscript');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'relativeTarget.typoscript'\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'RelativeRecursiveDirectory/relativeRecursive.typoscript'\n")->getNextLine())[0]);
        $subSubNode->setSplit();
        $subNode->addChild($subSubNode);
        $subSubSubNode = new AtImportInclude();
        $subSubSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/RelativeRecursiveDirectory/relativeTarget.typoscript');
        $subSubSubNode->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget.typoscript\n"));
        $subSubSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'relativeTarget.typoscript'\n")->getNextLine())[0]);
        $subSubNode->addChild($subSubSubNode);
        yield 'atImport with relative recursive include' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative6.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/hasRelative6.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import './RelativeSubDirectory/*.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        $subSubNode1 = new AtImportInclude();
        $subSubNode1->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/RelativeSubDirectory/relativeTarget.typoscript');
        $subSubNode1->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget.typoscript\n"));
        $subSubNode1->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import './RelativeSubDirectory/*.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode1);
        $subSubNode2 = new AtImportInclude();
        $subSubNode2->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/RelativeImport/RelativeSubDirectory/relativeTarget2.typoscript');
        $subSubNode2->setLineStream((new LosslessTokenizer())->tokenize("relativeTarget2.typoscript\n"));
        $subSubNode2->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import './RelativeSubDirectory/*.typoscript'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode2);
        yield 'atImport with relative sub directory include with wildcards' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal1.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal1.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/../InvalidImport/invalidPathTraversalTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        yield 'atImport single path traversal dot-dot-slash 1 is not allowed' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal2.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal2.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/../invalidPathTraversalTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        yield 'atImport single path traversal dot-dot-slash 2 is not allowed' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal3.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal3.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import '../invalidPathTraversalTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        yield 'atImport single path traversal relative dot-dot-slash 1 is not allowed' => [
            $atImportLineStream,
            $expectedTree,
        ];

        $atImportStatement = '@import "EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal4.typoscript"';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/InvalidImport/pathTraversal4.typoscript');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import './../invalidPathTraversalTarget.typoscript'\n"));
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subNode->setSplit();
        $expectedTree->addChild($subNode);
        yield 'atImport single path traversal relative dot-dot-slash 2 is not allowed' => [
            $atImportLineStream,
            $expectedTree,
        ];
    }

    #[DataProvider('buildTreeAtImportDataProvider')]
    #[Test]
    public function buildTreeAtImport(LineStream $lineStream, IncludeInterface $expectedTree): void
    {
        $tree = (new FileInclude());
        $tree->setLineStream($lineStream);
        $treeFromTokenStreamBuilder = $this->get(TreeFromLineStreamBuilder::class);
        $treeFromTokenStreamBuilder->buildTree($tree, 'setup', new LosslessTokenizer());
        self::assertEquals($expectedTree, $tree);
    }

    public static function buildTreeAtImportTsConfigDataProvider(): iterable
    {
        $atImportStatement = '@import \'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario3/mainInclude.tsconfig\'';
        $atImportLineStream = (new LosslessTokenizer())->tokenize($atImportStatement);
        $expectedTree = new FileInclude();
        $expectedTree->setLineStream($atImportLineStream);
        $expectedTree->setSplit();
        $subNode = new AtImportInclude();
        $subNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario3/mainInclude.tsconfig');
        $subNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario3/subInclude.tsconfig'\n"));
        $subNode->setSplit();
        $subNode->setOriginalLine(iterator_to_array($atImportLineStream->getNextLine())[0]);
        $subSubNode = new AtImportInclude();
        $subSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario3/subInclude.tsconfig');
        $subSubNode->setLineStream((new LosslessTokenizer())->tokenize("subInclude.typoscript\n"));
        $subSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario3/subInclude.tsconfig'\n")->getNextLine())[0]);
        $subNode->addChild($subSubNode);
        $expectedTree->addChild($subNode);
        yield 'atImport with dot-slash path traversal is allowed for tsconfig' => [
            $atImportLineStream,
            $expectedTree,
        ];
    }

    #[DataProvider('buildTreeAtImportTsConfigDataProvider')]
    #[Test]
    public function buildTreeAtImportTsConfig(LineStream $lineStream, IncludeInterface $expectedTree): void
    {
        $tree = (new FileInclude());
        $tree->setLineStream($lineStream);
        $treeFromTokenStreamBuilder = $this->get(TreeFromLineStreamBuilder::class);
        $treeFromTokenStreamBuilder->buildTree($tree, 'tsconfig', new LosslessTokenizer());
        self::assertEquals($expectedTree, $tree);
    }

    #[Test]
    public function atImportIncludesMagicTypoScriptRenderingForSimpleFile(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'] = [
            'core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup.']['defaultContentRendering'] = 'foo23';

        $expectedTree = new FileInclude();
        $expectedTree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript'\n"));
        $expectedTree->setSplit();
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript'\n")->getNextLine())[0]);
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new DefaultTypoScriptMagicKeyInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize('foo23'));
        $expectedTreeSubNode->setName('TYPO3_CONF_VARS defaultContentRendering for EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTree->addChild($expectedTreeSubNode);

        $tree = new FileInclude();
        $tree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript'\n"));
        $treeFromTokenStreamBuilder = $this->get(TreeFromLineStreamBuilder::class);
        $treeFromTokenStreamBuilder->buildTree($tree, 'setup', new LosslessTokenizer());
        self::assertEquals($expectedTree, $tree);
    }

    #[Test]
    public function atImportIncludesMagicTypoScriptRenderingForSimpleFileWithoutDotTypoScriptEnding(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'] = [
            'core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup.']['defaultContentRendering'] = 'foo23';

        $expectedTree = new FileInclude();
        $expectedTree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup'\n"));
        $expectedTree->setSplit();
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup'\n")->getNextLine())[0]);
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new DefaultTypoScriptMagicKeyInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize('foo23'));
        $expectedTreeSubNode->setName('TYPO3_CONF_VARS defaultContentRendering for EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTree->addChild($expectedTreeSubNode);

        $tree = new FileInclude();
        $tree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup'\n"));
        $treeFromTokenStreamBuilder = $this->get(TreeFromLineStreamBuilder::class);
        $treeFromTokenStreamBuilder->buildTree($tree, 'setup', new LosslessTokenizer());
        self::assertEquals($expectedTree, $tree);
    }

    #[Test]
    public function atImportIncludesMagicTypoScriptRenderingForDirectoryInclude(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'] = [
            'core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup.']['defaultContentRendering'] = 'foo23';

        $expectedTree = new FileInclude();
        $expectedTree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/'\n"));
        $expectedTree->setSplit();
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/'\n")->getNextLine())[0]);
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new DefaultTypoScriptMagicKeyInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize('foo23'));
        $expectedTreeSubNode->setName('TYPO3_CONF_VARS defaultContentRendering for EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/'\n")->getNextLine())[0]);
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/subInclude.typoscript');
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n"));
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/'\n")->getNextLine())[0]);
        $expectedTreeSubNode->setSplit();
        $expectedTreeSubSubNode = new AtImportInclude();
        $expectedTreeSubSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript');
        $expectedTreeSubSubNode->setLineStream((new LosslessTokenizer())->tokenize("SubDirectory.typoscript\n"));
        $expectedTreeSubSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/SubDirectory/SubDirectory.typoscript'\n")->getNextLine())[0]);
        $expectedTreeSubNode->addChild($expectedTreeSubSubNode);
        $expectedTree->addChild($expectedTreeSubNode);

        $tree = new FileInclude();
        $tree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/'\n"));
        $treeFromTokenStreamBuilder = $this->get(TreeFromLineStreamBuilder::class);
        $treeFromTokenStreamBuilder->buildTree($tree, 'setup', new LosslessTokenizer());
        self::assertEquals($expectedTree, $tree);
    }

    #[Test]
    public function atImportIncludesMagicTypoScriptRenderingForWildcardInclude(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'] = [
            'core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup.']['defaultContentRendering'] = 'foo23';

        $expectedTree = new FileInclude();
        $expectedTree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup*'\n"));
        $expectedTree->setSplit();
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("setup.typoscript\n"));
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup*'\n")->getNextLine())[0]);
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new DefaultTypoScriptMagicKeyInclude();
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize('foo23'));
        $expectedTreeSubNode->setName('TYPO3_CONF_VARS defaultContentRendering for EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup.typoscript');
        $expectedTree->addChild($expectedTreeSubNode);
        $expectedTreeSubNode = new AtImportInclude();
        $expectedTreeSubNode->setName('EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup2.typoscript');
        $expectedTreeSubNode->setOriginalLine(iterator_to_array((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup*'\n")->getNextLine())[0]);
        $expectedTreeSubNode->setLineStream((new LosslessTokenizer())->tokenize("setup2.typoscript\n"));
        $expectedTree->addChild($expectedTreeSubNode);

        $tree = new FileInclude();
        $tree->setLineStream((new LosslessTokenizer())->tokenize("@import 'EXT:core/Tests/Functional/TypoScript/IncludeTree/Fixtures/AtImport/AbsoluteImport/Scenario1/setup*'\n"));
        $treeFromTokenStreamBuilder = $this->get(TreeFromLineStreamBuilder::class);
        $treeFromTokenStreamBuilder->buildTree($tree, 'setup', new LosslessTokenizer());
        self::assertEquals($expectedTree, $tree);
    }
}
