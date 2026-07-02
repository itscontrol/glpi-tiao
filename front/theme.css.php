<?php
/**
 * CSS dinâmico para aproximar o GLPI da identidade visual do Tião.
 */
ob_start();
define('GLPI_ROOT', realpath(__DIR__ . '/../../../'));
$SECURITY_STRATEGY = 'no_check';
include(GLPI_ROOT . '/inc/includes.php');
ob_clean();

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: private, max-age=300');

$cfg = PluginTiaoConfig::get();
if (empty($cfg['theme_enabled'])) {
    exit;
}

$hex = static function (string $key) use ($cfg): string {
    $value = (string) ($cfg[$key] ?? '');
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : '#111827';
};

$px = static function (string $key) use ($cfg): string {
    $value = (string) ($cfg[$key] ?? '10');
    return preg_match('/^\d{1,2}$/', $value) ? min(24, max(0, (int) $value)) . 'px' : '10px';
};

$shadow = !empty($cfg['theme_shadow']) ? '0 8px 24px rgba(0,0,0,.22)' : 'none';
?>
:root {
  --tiao-body-bg: <?php echo $hex('theme_body_bg'); ?>;
  --tiao-header-bg: <?php echo $hex('theme_header_bg'); ?>;
  --tiao-sidebar-bg: <?php echo $hex('theme_sidebar_bg'); ?>;
  --tiao-sidebar-fg: <?php echo $hex('theme_sidebar_fg'); ?>;
  --tiao-card-bg: <?php echo $hex('theme_card_bg'); ?>;
  --tiao-card-hover-bg: <?php echo $hex('theme_card_hover_bg'); ?>;
  --tiao-border: <?php echo $hex('theme_border'); ?>;
  --tiao-primary: <?php echo $hex('theme_primary'); ?>;
  --tiao-primary-dark: <?php echo $hex('theme_primary_dark'); ?>;
  --tiao-accent: <?php echo $hex('theme_accent'); ?>;
  --tiao-text: <?php echo $hex('theme_text'); ?>;
  --tiao-text-muted: <?php echo $hex('theme_text_muted'); ?>;
  --tiao-text-disabled: <?php echo $hex('theme_text_disabled'); ?>;
  --tiao-input-bg: <?php echo $hex('theme_input_bg'); ?>;
  --tiao-input-border: <?php echo $hex('theme_input_border'); ?>;
  --tiao-table-bg: <?php echo $hex('theme_table_bg'); ?>;
  --tiao-table-alt-bg: <?php echo $hex('theme_table_alt_bg'); ?>;
  --tiao-table-hover: <?php echo $hex('theme_table_hover'); ?>;
  --tiao-radius: <?php echo $px('theme_radius'); ?>;
  --tiao-card-radius: <?php echo $px('theme_card_radius'); ?>;
  --tiao-shadow: <?php echo $shadow; ?>;

  --tblr-body-bg: var(--tiao-body-bg);
  --tblr-body-color: var(--tiao-text);
  --tblr-primary: var(--tiao-primary);
  --tblr-link-color: var(--tiao-primary);
  --tblr-link-hover-color: #8CC8FF;
  --tblr-card-bg: var(--tiao-card-bg);
  --tblr-border-color: var(--tiao-border);
  --tblr-muted: var(--tiao-text-muted);
}

body,
.page,
.page-wrapper,
.page-body {
  background: var(--tiao-body-bg) !important;
  color: var(--tiao-text) !important;
}

.navbar,
.navbar-vertical,
.navbar-expand-md,
.layout-navbar {
  background: var(--tiao-header-bg) !important;
  border-color: var(--tiao-border) !important;
}

.navbar-vertical,
.navbar-vertical .navbar-collapse,
.navbar-vertical .navbar-nav {
  background: var(--tiao-sidebar-bg) !important;
}

.navbar a,
.navbar .nav-link,
.navbar .dropdown-item,
.navbar-vertical .nav-link,
.navbar-vertical .nav-link-title,
.navbar-vertical .nav-link-icon {
  color: var(--tiao-sidebar-fg) !important;
}

.navbar .nav-link:hover,
.navbar .dropdown-item:hover,
.navbar-vertical .nav-link:hover,
.navbar-vertical .nav-link.active {
  background: var(--tiao-card-hover-bg) !important;
  color: #fff !important;
}

.card,
.modal-content,
.dropdown-menu,
.list-group-item,
.accordion-item {
  background: var(--tiao-card-bg) !important;
  border-color: var(--tiao-border) !important;
  border-radius: var(--tiao-card-radius) !important;
  box-shadow: var(--tiao-shadow);
  color: var(--tiao-text) !important;
}

.card-header,
.card-footer,
.modal-header,
.modal-footer,
.dropdown-header {
  background: transparent !important;
  border-color: var(--tiao-border) !important;
  color: var(--tiao-text) !important;
}

a,
.btn-link {
  color: var(--tiao-primary) !important;
}

a:hover,
.btn-link:hover {
  color: #8CC8FF !important;
}

.btn,
.form-control,
.form-select,
.input-group-text,
.dropdown-menu {
  border-radius: var(--tiao-radius) !important;
}

.btn-primary {
  background: var(--tiao-primary-dark) !important;
  border-color: var(--tiao-primary-dark) !important;
  color: #fff !important;
}

.btn-primary:hover,
.btn-primary:focus {
  filter: brightness(1.16);
}

.btn-danger,
.badge.bg-danger {
  background: var(--tiao-accent) !important;
  border-color: var(--tiao-accent) !important;
}

.btn-outline-secondary,
.btn-secondary {
  background: var(--tiao-card-hover-bg) !important;
  border-color: var(--tiao-border) !important;
  color: var(--tiao-text) !important;
}

.form-control,
.form-select,
.input-group-text,
textarea,
select {
  background-color: var(--tiao-input-bg) !important;
  border-color: var(--tiao-input-border) !important;
  color: var(--tiao-text) !important;
}

.form-control:focus,
.form-select:focus,
textarea:focus,
select:focus {
  border-color: var(--tiao-primary) !important;
  box-shadow: 0 0 0 .2rem rgba(79, 163, 255, .18) !important;
}

.form-control::placeholder,
.form-text,
.text-muted,
.text-secondary,
small {
  color: var(--tiao-text-muted) !important;
}

.disabled,
:disabled {
  color: var(--tiao-text-disabled) !important;
}

.table {
  --tblr-table-bg: var(--tiao-table-bg);
  --tblr-table-color: var(--tiao-text);
  --tblr-table-border-color: var(--tiao-border);
  color: var(--tiao-text) !important;
  border-color: var(--tiao-border) !important;
}

.table > :not(caption) > * > * {
  background-color: var(--tiao-table-bg) !important;
  border-color: var(--tiao-border) !important;
  color: var(--tiao-text) !important;
}

.table-striped > tbody > tr:nth-of-type(odd) > *,
.table tbody tr:nth-child(odd) > * {
  background-color: var(--tiao-table-alt-bg) !important;
}

.table-hover > tbody > tr:hover > *,
.table tbody tr:hover > * {
  background-color: var(--tiao-table-hover) !important;
  color: #fff !important;
}

.badge.bg-warning {
  background: #E6B800 !important;
  color: #111827 !important;
}

.badge.bg-info,
.badge.bg-primary {
  background: #2196F3 !important;
  color: #fff !important;
}

.badge.bg-success {
  background: #37C871 !important;
  color: #06162B !important;
}

.badge.bg-secondary {
  background: #7B68C5 !important;
  color: #fff !important;
}

#tiao-float-root {
  border-radius: var(--tiao-card-radius) !important;
  box-shadow: 0 8px 28px rgba(0,0,0,.32) !important;
}

/* Correção: áreas brancas do formulário do chamado */
.timeline,
.itil-timeline,
.itil-timeline .timeline-item,
.itil-timeline .timeline-content,
.rich_text_container,
.main-content,
.ticket-scrollable-content,
.layout-wrapper,
.item-main,
.item-form,
.itil-object,
.itil-object .card,
.itil-footer,
.form-footer,
.sticky-footer,
.ticket-footer,
.page-footer,
.bg-white,
.bg-body,
.bg-light {
  background: var(--tiao-body-bg) !important;
  color: var(--tiao-text) !important;
}

/* Barra inferior do chamado */
.itil-footer,
.form-footer,
.sticky-bottom,
.sticky-footer,
.ticket-footer,
.timeline-footer,
.navbar.fixed-bottom,
footer {
  background: var(--tiao-card-bg) !important;
  border-color: var(--tiao-border) !important;
  color: var(--tiao-text) !important;
}

/* Blocos de mensagem / acompanhamento */
.timeline-item .card,
.itil-timeline .card,
.rich_text_container,
.timeline-content {
  background: var(--tiao-card-bg) !important;
  border-color: var(--tiao-border) !important;
}

/* Painel direito do chamado */
aside,
.sidebar,
.right-panel,
.itil-sidebar,
.ticket-sidebar {
  background: var(--tiao-body-bg) !important;
  border-color: var(--tiao-border) !important;
}

/* Botões da barra inferior */
.itil-footer .btn,
.form-footer .btn,
.sticky-footer .btn,
.ticket-footer .btn {
  border-radius: var(--tiao-radius) !important;
}

/* Evita bloco branco em containers internos */
.bg-transparent,
.bg-body-tertiary {
  background-color: transparent !important;
}

/* Correção forte: barra inferior branca do chamado */
.timeline-buttons,
.timeline-buttons *,
.itil-footer,
.itil-footer *,
.form-buttons,
.form-buttons *,
.footer-actions,
.footer-actions *,
.sticky-actions,
.sticky-actions *,
.ticket-actions,
.ticket-actions *,
.rich_text_container + div,
.itil-timeline + div {
  background-color: var(--tiao-card-bg) !important;
  color: var(--tiao-text) !important;
  border-color: var(--tiao-border) !important;
}

/* Área branca específica embaixo do formulário */
body .bg-white,
body .bg-light,
body .bg-body,
body .bg-body-tertiary,
body [class*="footer"],
body [class*="bottom"],
body [class*="actions"] {
  background-color: var(--tiao-card-bg) !important;
  color: var(--tiao-text) !important;
  border-color: var(--tiao-border) !important;
}

/* Botões e ícones dentro da barra */
body [class*="footer"] .btn,
body [class*="bottom"] .btn,
body [class*="actions"] .btn {
  background-color: var(--tiao-primary-dark) !important;
  border-color: var(--tiao-primary-dark) !important;
  color: #fff !important;
}