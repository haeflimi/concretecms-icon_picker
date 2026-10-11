<?php

namespace IconPicker\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use IconPicker\Picker;

class PickIconsCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var Picker */
    protected $picker;

    public function __construct(Picker $picker)
    {
        $this->picker = $picker;
    }

    public function __invoke(PickIconsCommand $command)
    {
        $counts = $this->picker->pickPending(function (string $line) {
            $this->output->write($line);
        });
        $this->output->write(t('Icons picked: %s, without a fitting icon: %s, failed (will retry): %s.', $counts['picked'], $counts['none'], $counts['failed']));
    }
}
