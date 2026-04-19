<?php

declare(strict_types=1);

namespace Framework\Debug\Toolbar;

use Framework\Debug\Collector\CollectorInterface;

class ToolbarRenderer
{
    /** @param CollectorInterface[] $collectors */
    public function __construct(private readonly array $collectors) {}

    public function render(int $status): string
    {
        $statusClass = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default        => 'ok',
        };

        $tabs   = $this->renderTabs($status, $statusClass);
        $panels = $this->renderPanels();
        $css    = $this->css();
        $js     = $this->js();

        return <<<HTML
        <style>{$css}</style>
        <div id="__debugbar" class="__db-closed">
            <div class="__db-bar">
                <span class="__db-brand">⚙ Debug</span>
                <span class="__db-status __db-status-{$statusClass}">{$status}</span>
                {$tabs}
                <button class="__db-close" onclick="__dbClose()">✕</button>
            </div>
            <div class="__db-panels">{$panels}</div>
        </div>
        <script>{$js}</script>
        HTML;
    }

    // ------------------------------------------------------------------

    private function renderTabs(int $status, string $statusClass): string
    {
        $html = '';
        $first = true;

        foreach ($this->collectors as $collector) {
            $name    = htmlspecialchars($collector->getName());
            $summary = htmlspecialchars($collector->getSummary());
            $active  = $first ? ' __db-tab-active' : '';
            $first   = false;

            $html .= <<<HTML
            <button class="__db-tab{$active}" onclick="__dbTab('{$name}')" data-panel="{$name}">
                {$summary}
            </button>
            HTML;
        }

        return $html;
    }

    private function renderPanels(): string
    {
        $html  = '';
        $first = true;

        foreach ($this->collectors as $collector) {
            $name   = htmlspecialchars($collector->getName());
            $active = $first ? ' __db-panel-active' : '';
            $first  = false;
            $inner  = $collector->getPanel();

            $html .= <<<HTML
            <div id="__db-panel-{$name}" class="__db-panel{$active}">
                <div class="__db-panel-inner">{$inner}</div>
            </div>
            HTML;
        }

        return $html;
    }

    // ------------------------------------------------------------------
    // CSS inline — préfixé __db- pour éviter les conflits
    // ------------------------------------------------------------------

    private function css(): string
    {
        return <<<'CSS'
        #__debugbar *{box-sizing:border-box;margin:0;padding:0;font-family:monospace;font-size:12px}
        #__debugbar{position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#1e1e2e;color:#cdd6f4;border-top:2px solid #313244;transition:all .2s ease}
        .__db-bar{display:flex;align-items:center;gap:4px;padding:0 8px;height:32px;overflow-x:auto;white-space:nowrap}
        .__db-brand{color:#89b4fa;font-weight:bold;margin-right:8px;flex-shrink:0}
        .__db-status{padding:2px 8px;border-radius:4px;font-weight:bold;flex-shrink:0}
        .__db-status-ok{background:#a6e3a1;color:#1e1e2e}
        .__db-status-warning{background:#fab387;color:#1e1e2e}
        .__db-status-error{background:#f38ba8;color:#1e1e2e}
        .__db-tab{background:transparent;border:none;color:#a6adc8;cursor:pointer;padding:4px 10px;border-radius:4px;transition:all .15s;flex-shrink:0}
        .__db-tab:hover{background:#313244;color:#cdd6f4}
        .__db-tab-active{background:#313244;color:#89b4fa!important}
        .__db-close{margin-left:auto;background:transparent;border:none;color:#6c7086;cursor:pointer;font-size:14px;padding:4px 8px;flex-shrink:0}
        .__db-close:hover{color:#f38ba8}
        .__db-panels{display:none;max-height:280px;overflow-y:auto;border-top:1px solid #313244}
        #__debugbar:not(.__db-closed) .__db-panels{display:block}
        .__db-panel{display:none;padding:12px 16px}
        .__db-panel-active{display:block}
        .__db-panel-inner{display:flex;flex-direction:column;gap:8px}
        .__db-table{width:100%;border-collapse:collapse}
        .__db-table th,.__db-table td{padding:4px 10px;text-align:left;border-bottom:1px solid #313244}
        .__db-table th{color:#89b4fa;width:160px;font-weight:normal}
        .__db-query{background:#181825;border-radius:6px;padding:10px 12px;display:flex;flex-direction:column;gap:6px}
        .__db-query-head{display:flex;align-items:center;gap:8px}
        .__db-query-time{color:#a6adc8;margin-left:auto}
        .__db-pre{color:#a6e3a1;white-space:pre-wrap;word-break:break-all;font-size:11px}
        .__db-params{color:#a6adc8;font-size:11px}
        .__db-badge{background:#313244;padding:1px 6px;border-radius:4px;font-size:11px}
        .__db-badge-error,.__db-badge-critical,.__db-badge-emergency{background:#f38ba8;color:#1e1e2e}
        .__db-badge-warning{background:#fab387;color:#1e1e2e}
        .__db-badge-info,.__db-badge-notice{background:#89b4fa;color:#1e1e2e}
        .__db-badge-debug{background:#6c7086;color:#cdd6f4}
        .__db-log{display:flex;align-items:baseline;gap:8px;padding:4px 0;border-bottom:1px solid #313244}
        .__db-log-msg{flex:1}
        .__db-empty{color:#6c7086;font-style:italic}
        CSS;
    }

    // ------------------------------------------------------------------
    // JS inline
    // ------------------------------------------------------------------

    private function js(): string
    {
        return <<<'JS'
        (function(){
            var bar = document.getElementById('__debugbar');
            function __dbTab(name){
                bar.classList.remove('__db-closed');
                document.querySelectorAll('.__db-tab').forEach(function(t){
                    t.classList.toggle('__db-tab-active', t.dataset.panel === name);
                });
                document.querySelectorAll('.__db-panel').forEach(function(p){
                    p.classList.toggle('__db-panel-active', p.id === '__db-panel-' + name);
                });
            }
            function __dbClose(){
                bar.classList.add('__db-closed');
                document.querySelectorAll('.__db-tab').forEach(function(t){
                    t.classList.remove('__db-tab-active');
                });
            }
            window.__dbTab = __dbTab;
            window.__dbClose = __dbClose;
            // Ouvre le premier panneau au clic sur le premier tab
            var firstTab = bar.querySelector('.__db-tab-active');
            if(firstTab) firstTab.onclick();
        })();
        JS;
    }
}
