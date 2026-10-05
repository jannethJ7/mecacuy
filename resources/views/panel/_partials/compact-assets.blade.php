@once
@push('styles')
<style>
.mc-compact-page .mc-pro-header {min-height:0;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.mc-compact-page .mc-pro-header h2 {font-size:24px;margin:3px 0}
.mc-compact-page .mc-pro-header p:empty {display:none}
.mc-compact-page .mc-compact-toolbar {display:flex;align-items:end;flex-wrap:wrap;gap:12px;padding:14px}
.mc-compact-toolbar .mc-pro-search {flex:1 1 240px;min-width:0}
.mc-compact-select {display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700;color:var(--mc-text);flex:0 1 200px;min-width:140px}
.mc-compact-select select {width:100%;min-height:42px;border:1px solid var(--mc-pro-line);border-radius:12px;padding:8px 10px;background:var(--mc-pro-card);color:var(--mc-text)}
.mc-compact-select option {background:var(--mc-green-dark);color:var(--mc-text)}
.mc-compact-list {display:flex;flex-direction:column;gap:8px}
.mc-compact-page .mc-pro-sensor-card,.mc-compact-page .mc-pro-actuator-card,.mc-compact-camera {display:grid;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--mc-pro-line);border-radius:16px;background:var(--mc-pro-card);color:var(--mc-text);box-shadow:none;min-width:0}
.mc-compact-page .mc-pro-sensor-card {grid-template-columns:90px minmax(140px,1fr) 90px minmax(220px,1.3fr) auto}
.mc-compact-page .mc-pro-actuator-card {grid-template-columns:70px minmax(140px,1fr) minmax(230px,1.4fr) 130px auto}
.mc-compact-page .mc-pro-sensor-head,.mc-compact-page .mc-pro-actuator-head {display:flex;flex-direction:column;gap:6px;align-items:center;margin:0}
.mc-compact-page .mc-pro-sensor-icon,.mc-compact-page .mc-pro-actuator-icon,.mc-compact-camera-icon {width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:rgba(0,172,193,.12);color:#00a6bb;font-size:21px}
.mc-compact-page .mc-compact-identity h3,.mc-compact-camera h3 {font-size:14px;line-height:1.4;margin:0 0 3px;overflow-wrap:anywhere;color:var(--mc-text)}
.mc-compact-page .mc-compact-identity p,.mc-compact-camera p {font-size:12px;line-height:1.4;margin:0;color:var(--mc-muted-solid);overflow-wrap:anywhere}
.mc-compact-page .mc-pro-reading {margin:0;font-size:14px;display:flex;flex-wrap:wrap;gap:4px}
.mc-compact-page .mc-pro-reading strong {font-size:19px}
.mc-compact-page .mc-pro-mini-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin:0}
.mc-compact-page .mc-pro-mini-grid>div {background:none;border:0;border-radius:0;padding:0;min-width:0}
.mc-compact-page .mc-pro-mini-grid small {font-size:10px;color:var(--mc-muted-solid)}
.mc-compact-page .mc-pro-mini-grid strong {font-size:12px;overflow-wrap:anywhere;color:var(--mc-text)}
.mc-compact-page .mc-pro-card-actions {display:flex;flex-wrap:wrap;gap:6px;margin:0;align-items:center}
.mc-compact-page .mc-pro-card-actions form {margin:0}
.mc-compact-page .mc-pro-card-actions .mc-pro-btn {font-size:12px;padding:7px 9px;min-height:34px}
.mc-compact-page .mc-pro-icon-danger {width:34px;height:34px}
.mc-compact-page .mc-pro-toggle-wrap {display:flex;margin:0;padding:3px;gap:3px;min-width:0}
.mc-compact-page .mc-pro-toggle {padding:7px;min-height:32px;flex:1;font-size:12px}
.mc-compact-page .mc-pro-help {grid-column:2/-1;margin:0;font-size:11px}
.mc-compact-camera {grid-template-columns:34px 110px minmax(0,1fr)}
.mc-compact-camera .mc-camera-body {display:flex;align-items:center;justify-content:space-between;gap:14px;min-width:0;padding:0}
.mc-compact-camera small {font-size:11px;color:var(--mc-muted-solid);font-weight:700}
.mc-compact-page :is(a,button,input,select):focus-visible {outline:3px solid var(--mc-primary);outline-offset:3px}
@media(max-width:1200px) {
 .mc-compact-page .mc-pro-sensor-card,.mc-compact-page .mc-pro-actuator-card {grid-template-columns:70px minmax(0,1fr) minmax(200px,1fr)}
 .mc-compact-page .mc-pro-mini-grid {grid-column:2;grid-row:2}
 .mc-compact-page .mc-pro-card-actions {grid-column:3;grid-row:2;justify-content:flex-end}
 .mc-compact-page .mc-pro-reading,.mc-compact-page .mc-pro-toggle-wrap {justify-self:end}
 .mc-compact-page .mc-pro-help {grid-row:3}
}
@media(max-width:640px) {
 .mc-compact-page .mc-pro-header {padding:14px;flex-wrap:wrap}
 .mc-compact-page .mc-pro-header h2 {font-size:20px}
 .mc-compact-toolbar .mc-pro-search {flex-basis:100%}
 .mc-compact-select {flex:1 1 130px;min-width:0}
 .mc-compact-page .mc-pro-sensor-card,.mc-compact-page .mc-pro-actuator-card {grid-template-columns:64px minmax(0,1fr);gap:10px;padding:12px}
 .mc-compact-page .mc-pro-reading,.mc-compact-page .mc-pro-toggle-wrap {grid-column:2;grid-row:2;justify-self:start;width:auto;min-width:130px}
 .mc-compact-page .mc-pro-mini-grid {grid-column:1/-1;grid-row:3;grid-template-columns:repeat(2,minmax(0,1fr))}
 .mc-compact-page .mc-pro-card-actions {grid-column:1/-1;grid-row:4;justify-content:flex-start}
 .mc-compact-page .mc-pro-help {grid-column:1/-1;grid-row:5}
 .mc-compact-camera {grid-template-columns:34px minmax(0,1fr)}
 .mc-compact-camera>.mc-pro-badge {justify-self:start}
 .mc-compact-camera .mc-camera-body {grid-column:1/-1;align-items:flex-start;flex-direction:column;gap:10px}
}
html[data-mc-theme] .mc-compact-page .mc-pro-sensor-card,html[data-mc-theme] .mc-compact-page .mc-pro-actuator-card {background:var(--mc-pro-card) !important;border-color:var(--mc-pro-line) !important;box-shadow:none !important}.mc-compact-page .mc-pro-mini-grid>div {background:transparent !important;border:0 !important}.mc-compact-page .mc-pro-mini-grid strong {font-weight:700 !important}.mc-compact-page .mc-pro-search input {color:var(--mc-text) !important}body.mc-panel-body .mc-compact-page .mc-compact-toolbar {flex-direction:row;align-items:end}body.mc-panel-body .mc-compact-page .mc-pro-mini-grid>div {background:transparent !important}body.mc-panel-body .mc-compact-page .mc-pro-header {flex-direction:row;text-align:left}@media(max-width:640px){body.mc-panel-body .mc-compact-toolbar .mc-pro-search {width:100%;flex:1 1 100%}body.mc-panel-body .mc-compact-toolbar .mc-compact-select {flex:1 1 130px}body.mc-panel-body .mc-compact-page .mc-pro-header {align-items:center;padding:14px}} </style>
@endpush
@endonce




