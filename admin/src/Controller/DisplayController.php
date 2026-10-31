<?php
namespace FGcodework\Component\FgExtensionCleanup\Administrator\Controller;
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use RuntimeException;

final class DisplayController extends BaseController
{
    protected $default_view = 'cleanup';

    private const BULK_LIMIT = 200;

    public function repairFolder(): void
    {
        $this->guarded(function (): void {
            $info = $this->getModel('Cleanup')->repairFolder($this->input->post->getInt('extension_id', 0));
            $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_REPAIRED', $info);
            $this->setMessage(Text::_('COM_FGEXTENSIONCLEANUP_MSG_FOLDER_REPAIRED'), 'message');
        });
    }

    public function removeOrphanRecord(): void
    {
        $this->guarded(function (): void {
            $info = $this->getModel('Cleanup')->removeOrphanRecord($this->input->post->getInt('extension_id', 0));
            $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_REMOVED', $info);
            $this->setMessage(
                Text::_('COM_FGEXTENSIONCLEANUP_MSG_ORPHAN_REMOVED') . ' ' . Text::sprintf('COM_FGEXTENSIONCLEANUP_MSG_BACKUP_SAVED', $info['backup']),
                'message'
            );
        });
    }

    /** Bulk removal: every record goes through exactly the same checks (and gets its own backup) as a single removal. */
    public function removeOrphanRecords(): void
    {
        $this->guarded(function (): void {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->input->post->get('extension_id', [], 'array')))));
            $ids = array_slice($ids, 0, self::BULK_LIMIT);

            if (!$ids) {
                throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_NOTHING_SELECTED'));
            }

            $model = $this->getModel('Cleanup');
            $done = 0;
            $failed = [];

            foreach ($ids as $id) {
                try {
                    $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_REMOVED', $model->removeOrphanRecord($id));
                    $done++;
                } catch (RuntimeException $exception) {
                    $failed[] = '#' . $id . ': ' . $exception->getMessage();
                }
            }

            $this->reportBulk($done, $failed);
        });
    }

    public function removeUpdateSite(): void
    {
        $this->guarded(function (): void {
            $info = $this->getModel('Maintenance')->removeUpdateSite($this->input->post->getInt('update_site_id', 0));
            $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_US_REMOVED', $info);
            $this->setMessage(Text::_('COM_FGEXTENSIONCLEANUP_MSG_US_REMOVED') . ' ' . Text::sprintf('COM_FGEXTENSIONCLEANUP_MSG_BACKUP_SAVED', $info['backup']), 'message');
        });
    }

    public function removeUpdateSites(): void
    {
        $this->guarded(function (): void {
            $model = $this->getModel('Maintenance');
            $done = 0;
            $failed = [];

            foreach ($model->getUpdateSites()['orphans'] as $site) {
                if (!$site['removable']) {
                    continue;
                }
                try {
                    $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_US_REMOVED', $model->removeUpdateSite($site['id']));
                    $done++;
                } catch (RuntimeException $exception) {
                    $failed[] = '#' . $site['id'] . ': ' . $exception->getMessage();
                }
            }

            $this->reportBulk($done, $failed);
        });
    }

    public function restoreBackup(): void
    {
        $this->guarded(function (): void {
            $info = $this->getModel('Maintenance')->restoreBackup($this->input->post->getString('file', ''));
            $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_RESTORED', $info);
            $this->setMessage(
                Text::_('COM_FGEXTENSIONCLEANUP_MSG_RESTORED') . (!empty($info['menu_skipped']) ? ' ' . Text::_('COM_FGEXTENSIONCLEANUP_MSG_RESTORED_NO_MENU') : ''),
                'message'
            );
        });
    }

    public function deleteBackup(): void
    {
        $this->guarded(function (): void {
            $file = $this->input->post->getString('file', '');
            $this->getModel('Maintenance')->deleteBackup($file);
            $this->logAction('COM_FGEXTENSIONCLEANUP_ACTIONLOG_BACKUP_DELETED', ['id' => 0, 'name' => $file, 'type' => 'backup', 'element' => '']);
            $this->setMessage(Text::_('COM_FGEXTENSIONCLEANUP_MSG_BACKUP_DELETED'), 'message');
        });
    }

    /**
     * Token + core.admin check, error handling and redirect shared by every write action. Write actions need
     * core.admin, which is stricter than the core.manage required to open the component.
     */
    private function guarded(callable $action): void
    {
        $this->checkToken('post');

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_fgextensioncleanup')) {
            $this->setMessage(Text::_('COM_FGEXTENSIONCLEANUP_ERR_NO_PERMISSION'), 'error');
        } else {
            try {
                $action();
            } catch (RuntimeException $exception) {
                $this->setMessage($exception->getMessage(), 'warning');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_fgextensioncleanup', false));
    }

    /** @param string[] $failed */
    private function reportBulk(int $done, array $failed): void
    {
        if ($done > 0) {
            $this->app->enqueueMessage(Text::sprintf('COM_FGEXTENSIONCLEANUP_MSG_BULK_DONE', $done), 'message');
        }
        if ($failed) {
            $this->app->enqueueMessage(Text::sprintf('COM_FGEXTENSIONCLEANUP_MSG_BULK_FAILED', implode(' | ', $failed)), 'warning');
        }
        if ($done === 0 && !$failed) {
            $this->app->enqueueMessage(Text::_('COM_FGEXTENSIONCLEANUP_ERR_NOTHING_SELECTED'), 'notice');
        }
    }

    /**
     * Write an entry to Joomla's User Actions Log. A logging problem must never turn an already completed
     * database change into an error, so failures are swallowed.
     *
     * @param   array<string, mixed>  $info
     */
    private function logAction(string $messageKey, array $info): void
    {
        try {
            $user = $this->app->getIdentity();
            $message = $info + [
                'action' => 'fgcleanup',
                'title' => $info['name'] ?? '',
                'userid' => $user->id,
                'username' => $user->username,
                'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
            ];

            $this->app->bootComponent('com_actionlogs')
                ->getMVCFactory()
                ->createModel('Actionlog', 'Administrator', ['ignore_request' => true])
                ->addLog([$message], $messageKey, 'com_fgextensioncleanup', $user->id);
        } catch (\Throwable $exception) {
            // Action Logs component unavailable or disabled: nothing to do.
        }
    }
}
