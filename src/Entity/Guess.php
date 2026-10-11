<?php

namespace IconPicker\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The icon of one text (folder name, title, …). Only the table definition: IconPicker\Picker and the task read and
 * write the rows with plain queries.
 *
 * @ORM\Entity()
 * @ORM\Table(name="IconPickerGuesses",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="text", columns={"text"})},
 *     indexes={@ORM\Index(name="status", columns={"status"})}
 * )
 */
class Guess
{
    /** Waiting for the "Pick icons" task */
    const STATUS_PENDING = 'pending';
    /** Picked by n8n */
    const STATUS_AI = 'ai';
    /** Set on the dashboard; the task never changes it */
    const STATUS_MANUAL = 'manual';
    /** n8n gave no icon of the theme: the callers' fallback shows, not asked again */
    const STATUS_NONE = 'none';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * Normalized text, the key: entities decoded, tags stripped, whitespace collapsed, lower case.
     *
     * @ORM\Column(type="string", length=255)
     */
    protected $text;

    /**
     * The text as first seen, for the dashboard.
     *
     * @ORM\Column(type="string", length=255)
     */
    protected $label;

    /**
     * Hint for the pick (where the text shows, a few file names, …), updated while pending.
     *
     * @ORM\Column(type="string", length=500, options={"default": ""})
     */
    protected $context = '';

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $icon;

    /**
     * @ORM\Column(type="string", length=10)
     */
    protected $status = self::STATUS_PENDING;

    /**
     * Short reason given by the model.
     *
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected $reason = '';

    /**
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;
}
