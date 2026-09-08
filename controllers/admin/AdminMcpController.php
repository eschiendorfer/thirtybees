<?php

use Thirtybees\Core\Mcp\McpAuthentication;
use Thirtybees\Core\InitializationCallback;

/**
 * Small back-office surface for MCP settings, employee tokens and recent audit records.
 */
class AdminMcpControllerCore extends AdminController implements InitializationCallback
{
    /** @var string|null */
    private $generatedToken;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'mcp';
        $this->className = '';
        $this->list_no_link = true;

        parent::__construct();

        $endpoint = Tools::safeOutput($this->getMcpEndpoint());
        $this->fields_options = [
            'general' => [
                'title' => $this->l('MCP-Einstellungen'),
                'icon' => 'icon-cogs',
                'description' => Translate::ppTags(
                    sprintf($this->l('MCP-Endpunkt: [1]%s[/1]'), $endpoint),
                    ['<code>']
                ),
                'fields' => [
                    McpAuthentication::CONFIGURATION_ENABLED => [
                        'title' => $this->l('MCP aktivieren'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'type' => 'bool',
                        'visibility' => Shop::CONTEXT_ALL,
                    ],
                    McpAuthentication::CONFIGURATION_AUDIT_RETENTION_DAYS => [
                        'title' => $this->l('Aufbewahrungsdauer des Audit-Logs'),
                        'desc' => $this->l('Ältere Audit-Einträge werden beim Öffnen dieser Seite entfernt.'),
                        'validation' => 'isUnsignedInt',
                        'cast' => 'intval',
                        'type' => 'text',
                        'required' => true,
                        'class' => 'fixed-width-xs',
                        'suffix' => $this->l('Tage'),
                        'default' => 90,
                        'visibility' => Shop::CONTEXT_ALL,
                    ],
                ],
                'submit' => ['title' => $this->l('Speichern')],
            ],
        ];
    }

    public function viewAccess($disable = false)
    {
        return parent::viewAccess($disable)
            && (int) $this->context->employee->id_profile === (int) _PS_ADMIN_PROFILE_;
    }

    public function postProcess()
    {
        if ((int) $this->context->employee->id_profile !== (int) _PS_ADMIN_PROFILE_) {
            $this->errors[] = Tools::displayError($this->l('Nur Superadministratoren können den MCP-Zugriff verwalten.'));

            return false;
        }

        if (Tools::isSubmit('submitCreateMcpToken')) {
            $this->createToken();

            return false;
        }

        if (Tools::isSubmit('submitRevokeMcpToken')) {
            $this->revokeToken();

            return false;
        }

        if (Tools::isSubmit('submitOptions'.$this->table) || Tools::isSubmit('submitOptions')) {
            $retentionDays = Tools::getIntValue(McpAuthentication::CONFIGURATION_AUDIT_RETENTION_DAYS);
            if ($retentionDays < 1 || $retentionDays > 3650) {
                $this->errors[] = Tools::displayError('Die Aufbewahrungsdauer muss zwischen 1 und 3650 Tagen liegen.');

                return false;
            }
        }

        return parent::postProcess();
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_title = $this->l('MCP');
        parent::initPageHeaderToolbar();
    }

    public function renderList()
    {
        return '';
    }

    public function renderOptions()
    {
        $options = (string) parent::renderOptions();

        if (!McpToken::storageExists()) {
            $this->errors[] = Tools::displayError('Die MCP-Datenbanktabellen sind nicht installiert.');

            return $options;
        }

        $retentionDays = $this->getRetentionDays();
        try {
            McpAuditLog::purgeOlderThan($retentionDays);
        } catch (Throwable $e) {
            $this->warnings[] = $this->l('Alte MCP-Audit-Einträge konnten nicht bereinigt werden.');
        }

        $template = $this->createTemplate('panel.tpl');
        $template->assign([
            'mcp_action_url' => $this->context->link->getAdminLink('AdminMcp'),
            'mcp_employees' => Employee::getEmployees(true),
            'mcp_tokens' => $this->getTokens(),
            'mcp_audit_logs' => $this->getAuditLogs(),
            'mcp_generated_token' => $this->generatedToken,
        ]);

        return $options.$template->fetch();
    }

    private function createToken(): void
    {
        if (!McpToken::storageExists()) {
            $this->errors[] = Tools::displayError('Die MCP-Datenbanktabellen sind nicht installiert.');

            return;
        }

        try {
            $created = McpToken::createForEmployee(
                Tools::getIntValue('id_employee'),
                (string) Tools::getValue('token_name')
            );
            $this->generatedToken = $created['plaintext'];
            $this->confirmations[] = $this->l('MCP-Token erstellt. Kopieren Sie ihn jetzt; er kann später nicht erneut angezeigt werden.');
        } catch (Throwable $e) {
            $this->errors[] = Tools::displayError('Der MCP-Token konnte nicht erstellt werden. Prüfen Sie Mitarbeiter und Bezeichnung.');
        }
    }

    private function revokeToken(): void
    {
        $token = new McpToken(Tools::getIntValue('id_mcp_token'));
        if (!Validate::isLoadedObject($token)) {
            $this->errors[] = Tools::displayError('Der MCP-Token wurde nicht gefunden.');

            return;
        }

        if ($token->revoke()) {
            $this->confirmations[] = $this->l('MCP-Token gesperrt.');
        } else {
            $this->errors[] = Tools::displayError('Der MCP-Token konnte nicht gesperrt werden.');
        }
    }

    private function getRetentionDays(): int
    {
        $days = (int) Configuration::get(McpAuthentication::CONFIGURATION_AUDIT_RETENTION_DAYS);

        return $days > 0 ? min(3650, $days) : 90;
    }

    private function getMcpEndpoint(): string
    {
        $domain = (string) Configuration::get('PS_SHOP_DOMAIN_SSL');

        return 'https://'.$domain.'/mcp';
    }

    private function getTokens(): array
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('mt.`id_mcp_token`, mt.`name`, mt.`token_prefix`, mt.`active`, mt.`last_used_at`, mt.`date_add`')
                ->select('e.`firstname`, e.`lastname`, e.`active` AS `employee_active`')
                ->from('mcp_token', 'mt')
                ->leftJoin('employee', 'e', 'e.`id_employee` = mt.`id_employee`')
                ->orderBy('mt.`date_add` DESC')
        );
    }

    private function getAuditLogs(): array
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('al.`tool_name`, al.`success`, al.`duration_ms`, al.`result_count`, al.`date_add`')
                ->select('e.`firstname`, e.`lastname`, mt.`name` AS `token_name`, mt.`token_prefix`')
                ->from('mcp_audit_log', 'al')
                ->leftJoin('employee', 'e', 'e.`id_employee` = al.`id_employee`')
                ->leftJoin('mcp_token', 'mt', 'mt.`id_mcp_token` = al.`id_mcp_token`')
                ->orderBy('al.`date_add` DESC')
                ->limit(100)
        );
    }

    /**
     * Creates configuration defaults and the back-office tab during a Core Updater run.
     * Database tables are created from the McpToken and McpAuditLog ObjectModel definitions.
     *
     * @param Db $conn
     *
     * @throws PrestaShopException
     */
    public static function initializationCallback(Db $conn)
    {
        if (Configuration::get(McpAuthentication::CONFIGURATION_ENABLED) === false) {
            Configuration::updateGlobalValue(McpAuthentication::CONFIGURATION_ENABLED, 0);
        }
        if (Configuration::get(McpAuthentication::CONFIGURATION_AUDIT_RETENTION_DAYS) === false) {
            Configuration::updateGlobalValue(McpAuthentication::CONFIGURATION_AUDIT_RETENTION_DAYS, 90);
        }

        if (!Tab::getIdFromClassName('AdminMcp')) {
            $tab = new Tab();
            $tab->class_name = 'AdminMcp';
            $tab->id_parent = (int) Tab::getIdFromClassName('AdminTools');
            $tab->name = [];
            foreach (Language::getIDs() as $idLang) {
                $tab->name[$idLang] = 'MCP';
            }
            $tab->add();
        }
    }
}
