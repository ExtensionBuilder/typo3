/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import{html as p}from"lit";import a from"@typo3/core/ajax/ajax-request.js";import c from"@typo3/backend/modal.js";import{InfoBox as l}from"@typo3/install/renderable/info-box.js";import i from"@typo3/install/renderable/severity.js";import"@typo3/backend/element/spinner-element.js";import r from"@typo3/core/event/regular-event.js";import"@typo3/backend/element/progress-bar-element.js";class f{constructor(){this.rootSelector=".t3js-body",this.contentSelector=".t3js-module-body",this.scaffoldSelector=".t3js-scaffold",this.scaffoldContentOverlaySelector=".t3js-scaffold-content-overlay",this.scaffoldMenuToggleSelector=".t3js-topbar-button-modulemenu"}setContent(e){const t=this.rootContainer.querySelector(this.contentSelector);typeof e=="string"&&(e=document.createRange().createContextualFragment(e)),t.replaceChildren(e)}initialize(){this.rootContainer=document.querySelector(this.rootSelector),this.context=this.rootContainer.dataset.context??"",this.controller=this.rootContainer.dataset.controller??"",this.registerInstallToolRoutes(),new r("click",e=>{e.preventDefault(),this.logout()}).delegateTo(document,".t3js-login-lockInstallTool"),new r("click",e=>{e.preventDefault(),this.login()}).delegateTo(document,".t3js-login-login"),new r("keydown",e=>{e.key==="Enter"&&(e.preventDefault(),this.login())}).delegateTo(document,"#t3-install-form-password"),new r("click",(e,t)=>{e.preventDefault();const n=t.dataset.import,o=t.dataset.inline;if(typeof o<"u"&&parseInt(o,10)===1)import(n).then(({default:s})=>{s.initialize(t)});else{const s=t.closest(".card").querySelector(".card-title").innerHTML,h=t.dataset.modalSize||c.sizes.large;c.advanced({type:c.types.default,title:s,size:h,content:p`<div class=modal-loading><typo3-backend-spinner size=large></typo3-backend-spinner></div>`,additionalCssClasses:["install-tool-modal"],staticBackdrop:!0,callback:u=>{import(n).then(({default:g})=>{g.initialize(u)})}})}}).delegateTo(document,".card .btn"),this.context==="backend"?this.executeSilentConfigurationUpdate():this.preAccessCheck()}registerInstallToolRoutes(){typeof TYPO3.settings>"u"&&(TYPO3.settings={ajaxUrls:{icons:window.location.origin+window.location.pathname+"?install[controller]=icon&install[action]=getIcon"}})}getUrl(e,t,n){const o=new URL(location.href,window.origin);if(o.searchParams.set("install[controller]",t??this.controller),o.searchParams.set("install[context]",this.context),e!==void 0&&o.searchParams.set("install[action]",e),n!==void 0)for(const[d,s]of Object.entries(n))o.searchParams.set(d,s);return o.toString()}executeSilentConfigurationUpdate(){this.updateLoadingInfo("Checking session and executing silent configuration update"),new a(this.getUrl("executeSilentConfigurationUpdate","layout")).get({cache:"no-cache"}).then(async e=>{(await e.resolve()).success===!0?this.executeSilentTemplateFileUpdate():this.executeSilentConfigurationUpdate()},e=>{this.handleAjaxError(e)})}executeSilentTemplateFileUpdate(){this.updateLoadingInfo("Checking session and executing silent template file update"),new a(this.getUrl("executeSilentTemplateFileUpdate","layout")).get({cache:"no-cache"}).then(async e=>{(await e.resolve()).success===!0?this.executeSilentExtensionConfigurationSynchronization():this.executeSilentTemplateFileUpdate()},e=>{this.handleAjaxError(e)})}executeSilentExtensionConfigurationSynchronization(){this.updateLoadingInfo("Executing silent extension configuration synchronization"),new a(this.getUrl("executeSilentExtensionConfigurationSynchronization","layout")).get({cache:"no-cache"}).then(async e=>{(await e.resolve()).success===!0?this.loadMainLayout():this.setContent(l.create(i.error,"Something went wrong"))},e=>{this.handleAjaxError(e)})}loadMainLayout(){this.updateLoadingInfo("Loading main layout"),new a(this.getUrl("mainLayout","layout",{"install[module]":this.controller})).get({cache:"no-cache"}).then(async e=>{const t=await e.resolve();t.success===!0&&t.html!=="undefined"&&t.html.length>0?(this.rootContainer.innerHTML=t.html,this.context!=="backend"&&(this.rootContainer.querySelector('[data-installroute-controller="'+this.controller+'"]').classList.add("modulemenu-action-active"),this.registerScaffoldEvents()),this.loadCards()):this.rootContainer.replaceChildren(l.create(i.error,"Something went wrong"))},e=>{this.handleAjaxError(e)})}async handleAjaxError(e,t){if(e.response.status===403)this.context==="backend"?this.rootContainer.replaceChildren(l.create(i.error,"The Install Tool session expired. Please reload the backend and try again.")):this.checkEnableInstallToolFile();else{const o='<div class="t3js-infobox callout callout-sm callout-danger"><div class="callout-content"><div class="callout-title">Something went wrong</div><div class="callout-body"><p>Please use <b><a href="'+this.getUrl(void 0,"upgrade")+`">Check for broken extensions</a></b> to see if a loaded extension breaks this part of the Install Tool and unload it.</p><p>The box below may additionally reveal further details on what went wrong depending on your debug settings. It may help to temporarily switch to debug mode using <b>Settings > Configuration Presets > Debug settings.</b></p><p>If this error happens at an early state and no full exception back trace is shown, it may also help to manually increase debugging output in <strong>%config-dir%/system/settings.php</strong>:<code>['BE']['debug'] => true</code>, <code>['SYS']['devIPmask'] => '*'</code>, <code>['SYS']['displayErrors'] => 1</code>,<code>['SYS']['exceptionalErrors'] => 12290</code></p></div></div></div><div class="panel-group" role="tablist" aria-multiselectable="true"><div class="panel panel-default"><h3 class="panel-heading" role="tab" id="heading-error"><div class="panel-heading-row"><button type="button" data-bs-toggle="collapse" data-bs-parent="#accordion" data-bs-target="#collapse-error" aria-expanded="true" aria-controls="collapse-error" class="panel-button collapsed"><div class="panel-title"><strong>Ajax error</strong></div><span class="caret"></span></button></div></h3><div id="collapse-error" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading-error"><div class="panel-body">`+await e.response.text()+"</div></div></div></div>";typeof t<"u"?t.innerHTML=o:this.rootContainer.innerHTML=o}}checkEnableInstallToolFile(){new a(this.getUrl("checkEnableInstallToolFile")).get({cache:"no-cache"}).then(async e=>{(await e.resolve()).success===!0?this.checkLogin():this.showEnableInstallTool()},e=>{this.handleAjaxError(e)})}showEnableInstallTool(){new a(this.getUrl("showEnableInstallToolFile")).get({cache:"no-cache"}).then(async e=>{const t=await e.resolve();t.success===!0&&(this.rootContainer.innerHTML=t.html)},e=>{this.handleAjaxError(e)})}checkLogin(){new a(this.getUrl("checkLogin")).get({cache:"no-cache"}).then(async e=>{(await e.resolve()).success===!0?this.loadMainLayout():this.showLogin()},e=>{this.handleAjaxError(e)})}showLogin(){new a(this.getUrl("showLogin")).get({cache:"no-cache"}).then(async e=>{const t=await e.resolve();t.success===!0&&(this.rootContainer.innerHTML=t.html)},e=>{this.handleAjaxError(e)})}login(){const e=document.querySelector(".t3js-login-output");e.innerHTML="",new a(this.getUrl()).post({install:{action:"login",token:document.querySelector("[data-login-token]").dataset.loginToken,password:document.querySelector(".t3-install-form-input-text").value}}).then(async t=>{const n=await t.resolve();n.success===!0?this.executeSilentConfigurationUpdate():n.status.forEach(o=>{e.replaceChildren(l.create(o.severity,o.title,o.message))})},t=>{this.handleAjaxError(t)})}logout(){new a(this.getUrl("logout")).get({cache:"no-cache"}).then(async e=>{(await e.resolve()).success===!0&&this.showEnableInstallTool()},e=>{this.handleAjaxError(e)})}loadCards(){new a(this.getUrl("cards")).get({cache:"no-cache"}).then(async e=>{const t=await e.resolve();t.success===!0&&t.html!=="undefined"&&t.html.length>0?this.setContent(t.html):this.setContent(l.create(i.error,"Something went wrong"))},e=>{this.handleAjaxError(e)})}registerScaffoldEvents(){localStorage.getItem("typo3-install-modulesCollapsed")||localStorage.setItem("typo3-install-modulesCollapsed","false"),this.toggleMenu(localStorage.getItem("typo3-install-modulesCollapsed")==="true"),document.querySelector(this.scaffoldMenuToggleSelector).addEventListener("click",e=>{e.preventDefault(),this.toggleMenu()}),document.querySelector(this.scaffoldContentOverlaySelector).addEventListener("click",e=>{e.preventDefault(),this.toggleMenu(!0)}),document.querySelectorAll("[data-installroute-controller]").forEach(e=>{e.addEventListener("click",()=>{window.innerWidth<768&&localStorage.setItem("typo3-install-modulesCollapsed","true")})})}toggleMenu(e){const t=document.querySelector(this.scaffoldSelector),n="scaffold-modulemenu-expanded";typeof e>"u"&&(e=t.classList.contains(n)),t.classList.toggle(n,!e),localStorage.setItem("typo3-install-modulesCollapsed",e?"true":"false")}updateLoadingInfo(e){const t=this.rootContainer.querySelector("#t3js-ui-block-detail");t!==void 0&&t instanceof HTMLElement&&(t.innerText=e)}preAccessCheck(){this.updateLoadingInfo("Execute pre access check"),new a(this.getUrl("preAccessCheck","layout")).get({cache:"no-cache"}).then(async e=>{const t=await e.resolve();t.installToolLocked?this.checkEnableInstallToolFile():t.isAuthorized?this.executeSilentConfigurationUpdate():this.showLogin()},e=>{this.handleAjaxError(e)})}}var m=new f;export{m as default};
