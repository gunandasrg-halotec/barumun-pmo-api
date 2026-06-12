<?php


namespace App\Core\OpenApiSpecs;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

use League\CommonMark\Extension\Table\Table;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;

class AppMarkdown
{
    static function render(string $md)
    {

        $content = Str::of($md)->markdown(
            options: [
                'default_attributes' => [
                    Heading::class => [
                        'class' => static function (Heading $node) {
                            if ($node->getLevel() === 1) {
                                return 'title-main';
                            } else {
                                return null;
                            }
                        },
                    ],
                    Table::class => [
                        'class' => 'full-width',
                    ],
                    Paragraph::class => [
                        'class' => [],
                    ],
                    Link::class => [
                        'class' => 'btn btn-link',
                        'target' => '_blank',
                    ],
                ],
            ],
            extensions: [
                new \League\CommonMark\Extension\Attributes\AttributesExtension(),
                new \League\CommonMark\Extension\DescriptionList\DescriptionListExtension,
                new \League\CommonMark\Extension\Table\TableExtension,
                new \League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension
            ]
        ) . PHP_EOL;
        return $content;
    }

}