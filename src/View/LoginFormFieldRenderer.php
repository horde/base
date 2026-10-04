<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\View;

/**
 * Render login-form fields (username, password, 2FA, backend selects, language, mode, Oauth options)
 * from the `$loginparams` array produced by {@see \Horde\Horde\Login}
 * and the auth driver's `getLoginParams()`.
 *
 * Extracted from the duplicated rendering loops in login.php and
 * LoginService::renderFormFields() / renderSelectField().
 *
 */
class LoginFormFieldRenderer
{
    /**
     * Render all login parameters into form-field HTML.
     *
     * Returns an array with two keys so that callers that need to place
     * the mode selector separately (login.php) can do so, while callers
     * that don't care (LoginService) can simply concatenate both strings.
     *
     * @param array<string, array>  $loginparams       Field definitions
     *                              (from Login::buildLoginParams() merged
     *                              with auth-driver and app params).
     * @param string|null           $preservedUsername  Username to pre-fill
     *                              after a failed login (typically from a
     *                              query param or POST value).
     *
     * @return array{fields: string, modeSelector: string}
     *         'fields' contains all fields except new_lang and
     *         horde_select_view. 'modeSelector' contains the
     *         horde_select_view field (if present).
     */
    public function renderAll(array $loginparams, ?string $preservedUsername = null): array
    {
        $fields = '';
        $modeSelector = '';

        foreach ($loginparams as $key => $param) {
            // Language selector is rendered by a dedicated helper.
            if ($key === 'new_lang') {
                continue;
            }

            $label    = $param['label'] ?? ucfirst($key);
            $type     = $param['type'] ?? 'text';
            $value    = $param['value'] ?? '';
            $extra    = $param['extra'] ?? [];

            // Build wrapper-div attributes (e.g. id, style for JS toggling).
            $divAttrs = $this->buildAttrString($param['div'] ?? []);

            $fieldHtml = match (true) {
                $type === 'select'
                    => $this->renderSelectField($key, $label, $param['value'] ?? [], $divAttrs),
                $type === 'text' || $type === 'password'
                    => $this->renderInputField($key, $label, $type, $value, $extra, $divAttrs, $preservedUsername),
                default => '',
            };

            // Mode selector renders separately so it stays visible even
            // when credential fields are hidden (OAuth-only mode).
            if ($key === 'horde_select_view') {
                $modeSelector .= $fieldHtml;
            } else {
                $fields .= $fieldHtml;
            }
        }

        return ['fields' => $fields, 'modeSelector' => $modeSelector];
    }

    private function renderSelectField(
        string $key,
        string $label,
        array $options,
        string $divAttrs,
    ): string {
        $html  = '<div class="form-group"' . $divAttrs . '>';
        $html .= '<label for="' . $this->esc($key) . '" class="form-label">'
            . $this->esc($label) . '</label>';
        $html .= '<select id="' . $this->esc($key) . '" name="' . $this->esc($key)
            . '" class="form-input">';

        foreach ($options as $optKey => $optVal) {
            if ($optVal === null) {
                continue;
            }
            if (is_array($optVal)) {
                $selected = !empty($optVal['selected']) ? ' selected' : '';
                $safeKey  = is_scalar($optKey) ? (string) $optKey : '';
                $safeName = is_scalar($optVal['name'] ?? null)
                    ? (string) ($optVal['name'] ?? $safeKey)
                    : $safeKey;
                $html .= '<option value="' . $this->esc($safeKey) . '"' . $selected . '>'
                    . $this->esc($safeName) . '</option>';
            }
        }

        $html .= '</select></div>';
        return $html;
    }

    private function renderInputField(
        string $key,
        string $label,
        string $type,
        mixed $value,
        array $extra,
        string $divAttrs,
        ?string $preservedUsername,
    ): string {
        // ── Default extra attributes ──
        if (empty($extra) && $type === 'text') {
            $extra = ['autocapitalize' => 'off', 'autocorrect' => 'off'];
        }
        if ($key === 'horde_user' && !isset($extra['autocomplete'])) {
            $extra['autocomplete'] = 'username';
        }
        if ($type === 'password' && !isset($extra['autocomplete'])) {
            $extra['autocomplete'] = 'current-password';
        }

        $extraAttrs = $this->buildAttrString($extra);

        $inputValue = '';
        if ($type === 'password') {
            // Don't pre-fill password fields.
            $inputValue = '';
        } elseif ($key === 'horde_user' && $preservedUsername !== null) {
            $inputValue = $this->esc($preservedUsername);
        } else {
            $inputValue = $this->esc(is_array($value) ? '' : (string) $value);
        }

        $html  = '<div class="form-group"' . $divAttrs . '>';
        $html .= '<label for="' . $this->esc($key) . '" class="form-label">'
            . $this->esc($label) . '</label>';
        $html .= '<input type="' . $this->esc($type)
            . '" id="' . $this->esc($key)
            . '" name="' . $this->esc($key)
            . '" class="form-input" value="' . $inputValue . '"'
            . $extraAttrs . ' required>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Build a string of HTML attributes from a key/value array.
     *
     * @return string  Leading space + attributes, or empty string.
     */
    private function buildAttrString(array $attrs): string
    {
        $result = '';
        foreach ($attrs as $name => $value) {
            $result .= ' ' . $this->esc((string) $name)
                . '="' . $this->esc((string) $value) . '"';
        }
        return $result;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
