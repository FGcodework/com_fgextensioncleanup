<?php
namespace FGcodework\Component\FgExtensionCleanup\Administrator\View\Cleanup;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    protected array $rows = [];
    protected array $counts = [];
    protected array $typeCounts = [];
    protected bool $canAdmin = false;
    protected array $updateSites = ['orphans' => [], 'total' => 0];
    protected array $backups = [];
    protected array $otherOrphans = [];

    public function display($tpl = null): void
    {
        $scan = $this->getModel()->getScan();
        $this->rows = $scan['rows'];
        $this->counts = $scan['counts'];
        $this->typeCounts = $scan['type_counts'];
        $this->canAdmin = $this->getCurrentUser()->authorise('core.admin', 'com_fgextensioncleanup');

        $maintenance = Factory::getApplication()->bootComponent('com_fgextensioncleanup')
            ->getMVCFactory()
            ->createModel('Maintenance', 'Administrator', ['ignore_request' => true]);
        $this->updateSites = $maintenance->getUpdateSites();
        $this->otherOrphans = $maintenance->getOtherOrphans();
        if ($this->canAdmin) {
            // Backups can contain extension parameters, so only administrators get to see them.
            $this->backups = $maintenance->getBackups();
        }

        ToolbarHelper::title(Text::_('COM_FGEXTENSIONCLEANUP'), 'wrench');

        if ($this->canAdmin) {
            ToolbarHelper::preferences('com_fgextensioncleanup');
        }

        parent::display($tpl);
    }
}
