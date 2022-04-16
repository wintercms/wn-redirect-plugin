<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use System\Classes\PluginManager;
use Winter\Redirect\Models\Category;
use Winter\Redirect\Models\Redirect;

final class OptionHelper
{
    public static function getTargetTypeOptions(int $statusCode): array
    {
        if ($statusCode === 404 || $statusCode === 410) {
            return [
                Redirect::TARGET_TYPE_NONE => 'winter.redirect::lang.redirect.target_type_none',
            ];
        }

        return [
            Redirect::TARGET_TYPE_PATH_URL => 'winter.redirect::lang.redirect.target_type_path_or_url',
            Redirect::TARGET_TYPE_CMS_PAGE => 'winter.redirect::lang.redirect.target_type_cms_page',
            Redirect::TARGET_TYPE_STATIC_PAGE => 'winter.redirect::lang.redirect.target_type_static_page',
        ];
    }

    public static function getCmsPageOptions(): array
    {
        return ['' => '-- ' . e(trans('winter.redirect::lang.redirect.none')) . ' --' ] + Page::getNameList();
    }

    public static function getStaticPageOptions(): array
    {
        $options = ['' => '-- ' . e(trans('winter.redirect::lang.redirect.none')) . ' --' ];

        $hasPagesPlugin = PluginManager::instance()->hasPlugin('Winter.Pages');

        if (!$hasPagesPlugin) {
            return $options;
        }

        $pages = \Winter\Pages\Classes\Page::listInTheme(Theme::getActiveTheme());

        /** @var \Winter\Pages\Classes\Page $page */
        foreach ($pages as $page) {
            if (array_key_exists('title', $page->viewBag)) {
                $options[$page->getBaseFileName()] = $page->viewBag['title'];
            }
        }

        return $options;
    }

    public static function getCategoryOptions(): array
    {
        return Category::query()
            ->get(['id', 'name'])
            ->pluck('name', 'key')
            ->toArray();
    }
}
