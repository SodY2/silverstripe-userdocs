<?php

namespace Vulcan\UserDocs\Parsers;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\ShortcodeParser;
use Vulcan\UserDocs\Models\CodeTab;

class Shortcode
{
    use Injectable, Configurable;

    /**
     * Dynamically register all methods in this class as a shortcode. The method name will become
     * the shortcode, for example a function with the name of "code" will be registered as [code][/code]
     *
     * @return void
     */
    public static function registerShortcodes()
    {
        $methods = get_class_methods(self::class);

        foreach ($methods as $method) {
            if (in_array($method, [__FUNCTION__, 'config', 'create', 'singleton', 'stat', 'uninherited', 'set_stat'])) {
                continue;
            }

            ShortcodeParser::get('default')->register($method, [static::class, $method]);
        }
    }

    /**
     * @param array                $arguments
     * @param null|string          $content
     * @param null|ShortcodeParser $parser
     * @param string|null          $tagName
     *
     * @return \SilverStripe\ORM\FieldType\DBHTMLText|string
     */
    public static function code($arguments, $content = null, $parser = null, $tagName = null)
    {
        $htmlText = DBHTMLText::create();

        if (!trim($content)) {
            return $htmlText->setValue('');
        }

        if (!isset($arguments['display']) || $arguments['display'] == 'inline') {
            return ArrayData::create([
                'Content' => $htmlText->setValue($parser->parse($content))
            ])->renderWith('Vulcan\UserDocs\Parsers\Shortcode\CodeInline');
        }

        return $parser->parse(ArrayData::create([
            'Content'  => $htmlText->setValue($parser->parse($content)),
            'Language' => isset($arguments['lang']) ? $arguments['lang'] : null
        ])->renderWith('Vulcan\UserDocs\Parsers\Shortcode\CodeBlock'));
    }

    /**
     * @param array                $arguments
     * @param null|string          $content
     * @param null|ShortcodeParser $parser
     * @param string|null          $tagName
     *
     * @return DBHTMLText|string
     * @throws \Exception
     */
    public static function codetab($arguments, $content = null, $parser = null, $tagName = null)
    {
        $pageId = (isset($arguments['page_id'])) ? $arguments['page_id'] : Controller::curr()->ID;

        if (!$pageId) {
            throw new \Exception("Unable to retrieve a page ID from the current controller, and was also not supplied");
        }

        if (!isset($arguments['id'])) {
            return '[codetab: No id/slug was supplied]';
        }

        $record = CodeTab::get()->filter('PageID', $pageId)->filterAny([
            'ID'   => (int)$arguments['id'],
            'Slug' => $arguments['id']
        ])->first();

        if (!$record) {
            return '[codetab: id/slug was not found as a registered codetab for the active page]';
        }

        return $parser->parse($record->renderWith('Vulcan\UserDocs\Parsers\Shortcode\CodeTab'));
    }

    /**
     * Only show contents of shortcode if the user is currently logged in
     *
     * @param array                $arguments
     * @param null|string          $content
     * @param null|ShortcodeParser $parser
     * @param string|null          $tagName
     *
     * @return \SilverStripe\ORM\FieldType\DBHTMLText|string
     */
    public static function logged_in($arguments, $content = null, $parser = null, $tagName = null)
    {
        if (!$me = Security::getCurrentUser()) {
            return '';
        }

        return $parser->parse($content);
    }

    /**
     * Only show contents of shortcode if the user is not currently logged in
     *
     * @param array                $arguments
     * @param null|string          $content
     * @param null|ShortcodeParser $parser
     * @param string|null          $tagName
     *
     * @return \SilverStripe\ORM\FieldType\DBHTMLText|string
     */
    public static function not_logged_in($arguments, $content = null, $parser = null, $tagName = null)
    {
        if ($me = Security::getCurrentUser()) {
            return '';
        }

        return $parser->parse($content);
    }
}
