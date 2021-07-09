<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tokenizer\Transformer;

use PhpCsFixer\Tokenizer\AbstractTransformer;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Transform `|` operator into CT::T_TYPE_ALTERNATION in `function foo(Type1 | Type2 $x) {`
 *                                                    or `} catch (ExceptionType1 | ExceptionType2 $e) {`.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
final class TypeAlternationTransformer extends AbstractTransformer
{
    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        // needs to run after ArrayTypehintTransformer and TypeColonTransformer
        return -15;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPhpVersionId(): int
    {
        return 70100;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Tokens $tokens, Token $token, int $index): void
    {
        if (!$token->equals('|')) {
            return;
        }

        $prevIndex = $tokens->getPrevMeaningfulToken($index);

        if (!$tokens[$prevIndex]->isGivenKind([T_STRING, CT::T_ARRAY_TYPEHINT])) {
            return;
        }

        do {
            $prevIndex = $tokens->getPrevMeaningfulToken($prevIndex);

            if (null === $prevIndex) {
                return;
            }

            if (!$tokens[$prevIndex]->isGivenKind([T_NS_SEPARATOR, T_STRING])) {
                break;
            }
        } while (true);

        /** @var Token $prevToken */
        $prevToken = $tokens[$prevIndex];

        if ($prevToken->isGivenKind([
            CT::T_TYPE_COLON, // `:` is part of a function return type `foo(): A`
            CT::T_TYPE_ALTERNATION, // `|` is part of a union (chain) `X | Y`
            T_STATIC, T_VAR, T_PUBLIC, T_PROTECTED, T_PRIVATE, // `var $a;`, `private $a` or `public static $a`
        ])) {
            $this->replaceToken($tokens, $index);

            return;
        }

        if (!$prevToken->equals('(')) {
            return;
        }

        $prevPrevTokenIndex = $tokens->getPrevMeaningfulToken($prevIndex);

        /** @var Token $prePrevToken */
        $prePrevToken = $tokens[$prevPrevTokenIndex];

        if ($prePrevToken->isGivenKind([
            T_CATCH, // `|` is part of catch `catch(X |`
            T_FUNCTION, // `|` is part of an anonymous function variable `static function (X|Y`
        ])) {
            $this->replaceToken($tokens, $index);

            return;
        }

        if (\PHP_VERSION_ID >= 70400 && $prePrevToken->isGivenKind(T_FN)) {
            $this->replaceToken($tokens, $index); // `|` is part of an array function variable `fn(int|null`

            return;
        }

        if (
            $prePrevToken->isGivenKind(T_STRING)
            && $tokens[$tokens->getPrevMeaningfulToken($prevPrevTokenIndex)]->isGivenKind(T_FUNCTION)
        ) {
            // `|` is part of function variable `function Foo (X|Y`
            $this->replaceToken($tokens, $index);

            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomTokens(): array
    {
        return [CT::T_TYPE_ALTERNATION];
    }

    private function replaceToken(Tokens $tokens, int $index): void
    {
        $tokens[$index] = new Token([CT::T_TYPE_ALTERNATION, '|']);
    }
}
