<?php

/** Shared source of truth for release-owned data and production write protection. */
final class ReleaseDefinitionReadOnly extends RuntimeException {
    public const ERROR_CODE = 'release_definition_read_only';
    public const MESSAGE = 'Development settings included in releases cannot be modified directly in production. Please make your changes on the test server and deploy them through a release.';
    public function __construct() { parent::__construct(self::MESSAGE); }
}

final class ReleaseDefinitionPolicy {
    public const DATA_SETS = [
        'lang', 'email_format', 'db', 'constant_array', 'webhook_rule',
        'embed_app', 'public_assets', 'public_pages_registry', 'db_additionals',
        'dsp', 'dashboard', 'cron', 'api_studio',
    ];
    public const SELECTED_FILES = ['mcp_manage' => [
        'mcp_server_config.dat', 'mcp_functions.dat', 'mcp_tools.dat', 'mcp_tool_fields.dat',
    ]];

    public static function owns(string $class, string $table): bool {
        return in_array($class, self::DATA_SETS, true)
            || in_array($table.'.dat', self::SELECTED_FILES[$class] ?? [], true)
            || ($class === 'excel_grid' && $table === 'settings');
    }

    public static function assertEditable(string $dataRoot): void {
        require_once __DIR__.'/ExcelDefinitionGuard.php';
        try { $developer = ExcelDefinitionGuard::isDeveloper($dataRoot); }
        catch (ExcelDefinitionReadOnly $e) { throw new ReleaseDefinitionReadOnly(); }
        if (!$developer) throw new ReleaseDefinitionReadOnly();
    }

    public static function assertWrite(string $table, string $directory): void {
        $directory = realpath($directory) ?: rtrim($directory, '/');
        if (self::owns(basename($directory), $table)) self::assertEditable(dirname($directory));
    }
}
