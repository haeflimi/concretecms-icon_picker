<?php

namespace Concrete\Package\IconPicker;

use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use IconPicker\Command\Task\PickIconsController;
use IconPicker\Picker;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package implements ProviderAggregateInterface
{
    protected $pkgHandle = 'icon_picker';
    protected $appVersionRequired = '9.0.0';
    protected $phpVersionRequired = '8.0';
    protected $pkgVersion = '0.1.0';
    protected $pkgAutoloaderRegistries = [
        'src' => 'IconPicker',
    ];

    public function getPackageName()
    {
        return t('Icon Picker');
    }

    public function getPackageDescription()
    {
        return t('Picks theme icons for texts like folder names with an AI workflow in n8n.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'IconPicker\Entity',
        ]);
    }

    public function on_start()
    {
        $this->app->singleton(Picker::class);
        $this->app->make(TaskManager::class)->extend('pick_icons', function () {
            return $this->app->make(PickIconsController::class);
        });
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installContent($pkg);

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installContent($this->getPackageEntity());
    }

    protected function installContent($pkg): void
    {
        $importer = new ContentImporter();
        $importer->importContentFile($this->getPackagePath() . '/install.xml');

        $page = Page::getByPath('/dashboard/pages/icon_picker');
        if (!$page || $page->isError()) {
            $page = SinglePage::add('/dashboard/pages/icon_picker', $pkg);
            $page->update(['cName' => 'Icon Picker', 'cDescription' => 'Icons for folder names and other texts, picked by AI in n8n.']);
        }
    }
}
