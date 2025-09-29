<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Components\Fragment;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\UI\Components\{Layout\Body,
    Layout\Content,
    Layout\Div,
    Layout\Flash,
    Layout\Footer,
    Layout\Html,
    Layout\Layout,
    Layout\Wrapper};
use App\MoonShine\Resources\ImageUploadResource;
use MoonShine\MenuManager\MenuDivider;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuDivider::make(),
            MenuItem::make('site.menu.image_uploads', ImageUploadResource::class)
                ->translatable(),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);
        // $colorManager->primary('#00000');
    }

    public function build(): Layout
    {
        return Layout::make([
            Html::make([
                $this->getHeadComponent(),
                Body::make([
                    Wrapper::make([
                        // $this->getTopBarComponent(),
                        $this->getSidebarComponent(),

                        Div::make([
                            Fragment::make([
                                Flash::make(),

                                $this->getHeaderComponent(),

                                Content::make($this->getContentComponents()),

                                $this->getCustomFooter(),
                            ])->class('layout-page')->name(self::CONTENT_FRAGMENT_NAME),
                        ])->class('flex grow overflow-auto')->customAttributes(['id' => self::CONTENT_ID]),
                    ]),
                ]),
            ])
                ->customAttributes([
                    'lang' => $this->getHeadLang(),
                ])
                ->withAlpineJs()
                ->withThemes($this->isAlwaysDark()),
        ]);
    }

    private function getCustomFooter(): Footer
    {
        return Footer::make()
            ->copyright($this->getCustomFooterCopyright())
            ->menu($this->getCustomFooterMenu());
    }


    private function getCustomFooterMenu(): array
    {
        return [
//            'https://moonshine-laravel.com/docs' => 'Documentation',
        ];
    }

    private function getCustomFooterCopyright(): string
    {
        return \sprintf(
            <<<'HTML'
                &copy; %d %s
                HTML,
            now()->year,
            config('app.name')
        );
    }
}
