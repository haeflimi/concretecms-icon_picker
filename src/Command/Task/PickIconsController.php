<?php

namespace IconPicker\Command\Task;

use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\CommandTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use IconPicker\Command\PickIconsCommand;

class PickIconsController extends AbstractController
{
    public function getName(): string
    {
        return t('Pick Icons');
    }

    public function getDescription(): string
    {
        return t('Asks the n8n workflow for the icons of new texts like folder names; only icons of the theme are stored.');
    }

    public function getConsoleCommandName(): string
    {
        return 'pick-icons';
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new CommandTaskRunner($task, new PickIconsCommand(), t('Pick Icons finished.'));
    }
}
