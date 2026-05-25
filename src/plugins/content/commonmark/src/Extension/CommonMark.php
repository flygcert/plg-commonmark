<?php

/**
 * @package     CommonMark
 *
 * @copyright   (C) 2007 - 2026 Flygcert FZE. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Flygcert\Plugin\Content\CommonMark\Extension;

use Joomla\CMS\Event\Content;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use JSW\Figure\FigureExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * CommonMark plugin for LMS.
 *
 * @since 4.0.0
 */
final class CommonMark extends CMSPlugin implements SubscriberInterface
{
    /**
     * Cached CommonMark converter instance.
     *
     * @var   MarkdownConverter|null
     * @since 5.0.1
     */
    private static ?MarkdownConverter $converter = null;

    /**
     * Load the language file on instantiation.
     *
     * @var   boolean
     * @since 4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings
     *
     * @since   4.0.0
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        $autoload = __DIR__ . '/../../vendor/autoload.php';

        // Initialize auto-loading.
        if (!file_exists($autoload)) {
            throw new \LogicException('Please run composer in commonmark plugin folder!');
        }

        require_once $autoload;
    }

    /**
     * @inheritDoc
     *
     * @return  string[]
     *
     * @since  4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Method for process content.
     *
     * @param   Content\ContentPrepareEvent  $event  Event instance
     *
     * @return  void
     */
    public function onContentPrepare(Content\ContentPrepareEvent $event): void
    {
        // Don't run if in the API Application
        // Don't run this plugin when the content is being indexed
        if ($this->getApplication()->isClient('api') || $event->getContext() === 'com_finder.indexer') {
            return;
        }

        $converter = $this->getConverter();
        $item      = $event->getItem();

        if (isset($item->process)) {
            foreach ($item->process as $path) {
                $nodes = array_values(array_filter(explode('.', $path), 'strlen'));

                $node = $item;

                for ($i = 0, $n = \count($nodes) - 1; $i < $n; $i++) {
                    if (\is_object($node)) {
                        $node = &$node->{$nodes[$i]};

                        continue;
                    }

                    if (\is_array($node)) {
                        $node = &$node[$nodes[$i]];
                    }
                }

                if (isset($node->{$nodes[$i]})) {
                    $data = \is_string($node->{$nodes[$i]})
                            ? $converter->convert($node->{$nodes[$i]})->getContent()
                            : $node->{$nodes[$i]};

                    if (\is_object($node)) {
                        $node->{$nodes[$i]} = $data;
                    } elseif (\is_array($node)) {
                        $node[$nodes[$i]] = $data;
                    }
                }
            }
        }


        if (property_exists($item, 'text')) {
            $item->text = \is_string($item->text) ? $converter->convert($item->text)->getContent() : $item->text;
        }

        if (property_exists($item, 'page')) {
            $item->page->text = \is_string($item->page->text) ? $converter->convert($item->page->text)->getContent() : $item->page->text;
        }

        if (property_exists($item, 'pages')) {
            foreach ($item->pages as $node) {
                $node->text = \is_string($node->text) ? $converter->convert($node->text)->getContent() : $node->text;
            }
        }
    }

    /**
     * Get the commonmark converter.
     *
     * @return MarkdownConverter
     *
     * @since 4.0.0
     */
    public static function getConverter(): MarkdownConverter
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        $config = [
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 100,
            'external_link'      => [
                'internal_hosts'     => 'flygcert.com',
                'open_in_new_window' => true,
                'nofollow'           => '',
                'noopener'           => 'external',
                'noreferrer'         => 'external',
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new FigureExtension());
        $environment->addExtension(new FrontMatterExtension());

        self::$converter = new MarkdownConverter($environment);

        return self::$converter;
    }

    /**
     * Get the commonmark parser.
     *
     * @return FrontMatterParser
     *
     * @since 4.0.0
     */
    public static function getParser(): FrontMatterParser
    {
        return new FrontMatterParser(new SymfonyYamlFrontMatterParser());
    }
}
