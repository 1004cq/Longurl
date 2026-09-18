<?php
declare(strict_types=1);

final class I18n
{
    private const SUPPORTED = ['en', 'zh'];

    private static string $locale = 'en';

    private static array $messages = [
        'en' => [
            'language' => 'Language',
            'english' => 'English',
            'chinese' => '中文',
            'tagline' => "Your URL isn't long enough.",
            'description' => 'Make your URLs unnecessarily long.',
            'online' => 'Online',
            'eyebrow' => 'A long URL generator',
            'hero_title' => 'Make it',
            'hero_accent' => 'unnecessarily long.',
            'input_title' => 'Where should it go?',
            'length_title' => 'How long should it be?',
            'paste' => 'Paste',
            'valid_url' => 'Ready to transform',
            'original_url' => 'Original URL',
            'length' => 'Length',
            'generate' => 'Generate long URL',
            'generating' => 'Generating…',
            'long_url' => 'Long URL',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'open' => 'Open',
            'lost_title' => 'This e is lost.',
            'lost_text' => 'That path is only e’s, but nobody lives there yet.',
            'lost_signal' => 'SIGNAL NOT FOUND',
            'lost_hint' => 'The destination you are looking for may have expired, moved, or never existed.',
            'back_home' => 'Back home',
            'dashboard' => 'Dashboard',
            'links' => 'Links',
            'analytics' => 'Analytics',
            'api' => 'API',
            'settings' => 'Settings',
            'logout' => 'Logout',
            'open_site' => 'Open site',
            'active' => 'Active',
            'clicks' => 'Clicks',
            'today' => 'Today',
            'traffic' => 'Traffic',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'recent_links' => 'Recent links',
            'recent_visits' => 'Recent visits',
            'view_all' => 'View all',
            'top_links' => 'Top links',
            'search_placeholder' => 'Search target URL or e length',
            'search' => 'Search',
            'export_csv' => 'Export CSV',
            'status' => 'Status',
            'expires' => 'Expires',
            'actions' => 'Actions',
            'edit' => 'Edit',
            'disable' => 'Disable',
            'enable' => 'Enable',
            'delete' => 'Delete',
            'edit_link' => 'Edit link',
            'target_url' => 'Target URL',
            'expires_at' => 'Expires at',
            'save_changes' => 'Save changes',
            'raw_events' => 'Raw events',
            'endpoint' => 'Endpoint',
            'api_secret' => 'API secret',
            'current_token' => 'Current token',
            'rotate_token' => 'Rotate token',
            'application' => 'Application',
            'url_settings' => 'URL settings',
            'base_url' => 'Base URL',
            'min_e_length' => 'Minimum e length',
            'max_e_length' => 'Maximum e length',
            'save_settings' => 'Save settings',
            'security' => 'Security',
            'change_password' => 'Change admin password',
            'current_password' => 'Current password',
            'new_password' => 'New password',
            'confirm_password' => 'Confirm new password',
            'update_password' => 'Update password',
            'sign_in_text' => 'Sign in to the long URL console.',
            'password' => 'Password',
            'sign_in' => 'Sign in',
            'previous' => 'Previous',
            'next' => 'Next',
            'never' => 'Never',
            'disabled' => 'Disabled',
            'expired' => 'Expired',
        ],
        'zh' => [
            'language' => '语言',
            'english' => 'English',
            'chinese' => '中文',
            'tagline' => '你的链接还不够长。',
            'description' => '把链接变得毫无必要地长。',
            'online' => '系统在线',
            'eyebrow' => '超长链接生成器',
            'hero_title' => '让它变得',
            'hero_accent' => '毫无必要地长。',
            'input_title' => '它要指向哪里？',
            'length_title' => '你想要多长？',
            'paste' => '粘贴',
            'valid_url' => '准备转换',
            'original_url' => '原始链接',
            'length' => '长度',
            'generate' => '生成长链接',
            'generating' => '生成中…',
            'long_url' => '长链接',
            'copy' => '复制',
            'copied' => '已复制',
            'open' => '打开',
            'lost_title' => '这个 e 迷路了。',
            'lost_text' => '这里全是 e，但还没有链接住在这里。',
            'lost_signal' => '信号未找到',
            'lost_hint' => '你要找的目的地可能已过期、已移动，或者从未存在。',
            'back_home' => '返回首页',
            'dashboard' => '仪表盘',
            'links' => '链接',
            'analytics' => '统计',
            'api' => 'API',
            'settings' => '设置',
            'logout' => '退出',
            'open_site' => '打开网站',
            'active' => '有效',
            'clicks' => '访问量',
            'today' => '今日',
            'traffic' => '流量',
            'last_7_days' => '最近 7 天',
            'last_30_days' => '最近 30 天',
            'recent_links' => '最近链接',
            'recent_visits' => '最近访问',
            'view_all' => '查看全部',
            'top_links' => '热门链接',
            'search_placeholder' => '搜索目标 URL 或 e 长度',
            'search' => '搜索',
            'export_csv' => '导出 CSV',
            'status' => '状态',
            'expires' => '过期时间',
            'actions' => '操作',
            'edit' => '编辑',
            'disable' => '停用',
            'enable' => '启用',
            'delete' => '删除',
            'edit_link' => '编辑链接',
            'target_url' => '目标 URL',
            'expires_at' => '过期时间',
            'save_changes' => '保存修改',
            'raw_events' => '原始访问记录',
            'endpoint' => '接口',
            'api_secret' => 'API 密钥',
            'current_token' => '当前 Token',
            'rotate_token' => '轮换 Token',
            'application' => '应用',
            'url_settings' => 'URL 设置',
            'base_url' => '站点地址',
            'min_e_length' => '最小 e 长度',
            'max_e_length' => '最大 e 长度',
            'save_settings' => '保存设置',
            'security' => '安全',
            'change_password' => '修改管理员密码',
            'current_password' => '当前密码',
            'new_password' => '新密码',
            'confirm_password' => '确认新密码',
            'update_password' => '修改密码',
            'sign_in_text' => '登录长链接管理后台。',
            'password' => '密码',
            'sign_in' => '登录',
            'previous' => '上一页',
            'next' => '下一页',
            'never' => '永不过期',
            'disabled' => '已停用',
            'expired' => '已过期',
        ],
    ];

    public static function init(): void
    {
        $requested = strtolower(trim((string) ($_GET['lang'] ?? '')));
        if (in_array($requested, self::SUPPORTED, true)) {
            $_SESSION['_locale'] = $requested;
        }

        $saved = strtolower((string) ($_SESSION['_locale'] ?? ''));
        if (in_array($saved, self::SUPPORTED, true)) {
            self::$locale = $saved;
            return;
        }

        $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        self::$locale = str_starts_with($header, 'zh') ? 'zh' : 'en';
        $_SESSION['_locale'] = self::$locale;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function t(string $key): string
    {
        return (string) (self::$messages[self::$locale][$key]
            ?? self::$messages['en'][$key]
            ?? $key);
    }

    public static function switchUrl(string $locale): string
    {
        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'en';
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $query = $_GET;
        $query['lang'] = $locale;

        return $path . '?' . http_build_query($query);
    }
}
