<?php

namespace IconPicker;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

/**
 * Asks the n8n workflow (n8n/icon-picker.n8n.json) for the icon of a text. The workflow reads the icons from the
 * site's iconset.json (IconSetFile) and lets the model choose; model and prompt live in n8n.
 */
class N8nClient
{
    /** The header the workflow's Header Auth credential checks */
    const SECRET_HEADER = 'X-Icon-Picker-Secret';

    /** @var Client */
    protected $http;

    /** @var Repository */
    protected $config;

    public function __construct(Client $http, Repository $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    public function isConfigured(): bool
    {
        return $this->getUrl() !== '';
    }

    public function isEnabled(): bool
    {
        return $this->isConfigured() && (bool) $this->config->get('icon_picker::n8n.enabled');
    }

    public function getUrl(): string
    {
        return trim((string) $this->config->get('icon_picker::n8n.url'));
    }

    public function getSecret(): string
    {
        return (string) $this->config->get('icon_picker::n8n.secret');
    }

    /**
     * @throws RuntimeException when n8n can't be reached or answers with an error: try again later
     *
     * @return array{icon: ?string, reason: string} the icon as the workflow returned it (not checked yet)
     */
    public function ask(string $text, string $context = ''): array
    {
        try {
            $response = $this->http->request('POST', $this->getUrl(), [
                'json' => ['text' => $text, 'context' => $context],
                'headers' => [self::SECRET_HEADER => $this->getSecret(), 'Accept' => 'application/json'],
                'timeout' => 30,
                'connect_timeout' => 5,
            ]);
        } catch (ClientException $e) {
            throw new RuntimeException(t('n8n answered with HTTP %s. Check the webhook URL and the secret.', $e->getResponse()->getStatusCode()), 0, $e);
        } catch (\Throwable $e) {
            throw new RuntimeException(t('n8n could not be reached: %s', $e->getMessage()), 0, $e);
        }
        $data = json_decode((string) $response->getBody(), true);
        // the "Respond to Webhook" node returns the item; lists are unwrapped for other setups
        if (is_array($data) && isset($data[0])) {
            $data = $data[0];
        }
        if (!is_array($data)) {
            throw new RuntimeException(t('n8n did not answer with JSON.'));
        }
        $icon = trim((string) ($data['icon'] ?? ''));

        return ['icon' => $icon !== '' ? $icon : null, 'reason' => mb_substr(trim((string) ($data['reason'] ?? '')), 0, 255)];
    }
}
