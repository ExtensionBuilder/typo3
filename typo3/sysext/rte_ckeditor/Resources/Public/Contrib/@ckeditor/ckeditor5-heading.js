import{Command as e,Plugin as t}from"@ckeditor/ckeditor5-core";import{Paragraph as o}from"@ckeditor/ckeditor5-paragraph";import{first as n,priorities as i,Collection as a}from"@ckeditor/ckeditor5-utils";import{Model as r,createDropdown as s,addListToDropdown as d,ButtonView as c}from"@ckeditor/ckeditor5-ui";import{DowncastWriter as l,enablePlaceholder as h,hidePlaceholder as m,needsPlaceholder as g,showPlaceholder as u}from"@ckeditor/ckeditor5-engine";
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */class p extends e{constructor(e,t){super(e),this.modelElements=t}refresh(){const e=n(this.editor.model.document.selection.getSelectedBlocks());this.value=!!e&&this.modelElements.includes(e.name)&&e.name,this.isEnabled=!!e&&this.modelElements.some((t=>f(e,t,this.editor.model.schema)))}execute(e){const t=this.editor.model,o=t.document,n=e.value;t.change((e=>{const i=Array.from(o.selection.getSelectedBlocks()).filter((e=>f(e,n,t.schema)));for(const t of i)t.is("element",n)||e.rename(t,n)}))}}function f(e,t,o){return o.checkChild(e.parent,t)&&!o.isObject(e)}
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */const v="paragraph";class w extends t{static get pluginName(){return"HeadingEditing"}constructor(e){super(e),e.config.define("heading",{options:[{model:"paragraph",title:"Paragraph",class:"ck-heading_paragraph"},{model:"heading1",view:"h2",title:"Heading 1",class:"ck-heading_heading1"},{model:"heading2",view:"h3",title:"Heading 2",class:"ck-heading_heading2"},{model:"heading3",view:"h4",title:"Heading 3",class:"ck-heading_heading3"}]})}static get requires(){return[o]}init(){const e=this.editor,t=e.config.get("heading.options"),o=[];for(const n of t)"paragraph"!==n.model&&(e.model.schema.register(n.model,{inheritAllFrom:"$block"}),e.conversion.elementToElement(n),o.push(n.model));this._addDefaultH1Conversion(e),e.commands.add("heading",new p(e,o))}afterInit(){const e=this.editor,t=e.commands.get("enter"),o=e.config.get("heading.options");t&&this.listenTo(t,"afterExecute",((t,n)=>{const i=e.model.document.selection.getFirstPosition().parent;o.some((e=>i.is("element",e.model)))&&!i.is("element",v)&&0===i.childCount&&n.writer.rename(i,v)}))}_addDefaultH1Conversion(e){e.conversion.for("upcast").elementToElement({model:"heading1",view:"h1",converterPriority:i.low+1})}}
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */function x(e){const t=e.t,o={Paragraph:t("Paragraph"),"Heading 1":t("Heading 1"),"Heading 2":t("Heading 2"),"Heading 3":t("Heading 3"),"Heading 4":t("Heading 4"),"Heading 5":t("Heading 5"),"Heading 6":t("Heading 6")};return e.config.get("heading.options").map((e=>{const t=o[e.title];return t&&t!=e.title&&(e.title=t),e}))}!function(e,{insertAt:t}={}){if(!e||"undefined"==typeof document)return;const o=document.head||document.getElementsByTagName("head")[0],n=document.createElement("style");n.type="text/css",window.litNonce&&n.setAttribute("nonce",window.litNonce),"top"===t&&o.firstChild?o.insertBefore(n,o.firstChild):o.appendChild(n),n.styleSheet?n.styleSheet.cssText=e:n.appendChild(document.createTextNode(e))}(".ck.ck-heading_heading1{font-size:20px}.ck.ck-heading_heading2{font-size:17px}.ck.ck-heading_heading3{font-size:14px}.ck[class*=ck-heading_heading]{font-weight:700}.ck.ck-dropdown.ck-heading-dropdown .ck-dropdown__button .ck-button__label{width:8em}.ck.ck-dropdown.ck-heading-dropdown .ck-dropdown__panel .ck-list__item{min-width:18em}");
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */
class k extends t{static get pluginName(){return"HeadingUI"}init(){const e=this.editor,t=e.t,o=x(e),n=t("Choose heading"),i=t("Heading");e.ui.componentFactory.add("heading",(t=>{const c={},l=new a,h=e.commands.get("heading"),m=e.commands.get("paragraph"),g=[h];for(const e of o){const t={type:"button",model:new r({label:e.title,class:e.class,role:"menuitemradio",withText:!0})};"paragraph"===e.model?(t.model.bind("isOn").to(m,"value"),t.model.set("commandName","paragraph"),g.push(m)):(t.model.bind("isOn").to(h,"value",(t=>t===e.model)),t.model.set({commandName:"heading",commandValue:e.model})),l.add(t),c[e.model]=e.title}const u=s(t);return d(u,l,{ariaLabel:i,role:"menu"}),u.buttonView.set({ariaLabel:i,ariaLabelledBy:void 0,isOn:!1,withText:!0,tooltip:i}),u.extendTemplate({attributes:{class:["ck-heading-dropdown"]}}),u.bind("isEnabled").toMany(g,"isEnabled",((...e)=>e.some((e=>e)))),u.buttonView.bind("label").to(h,"value",m,"value",((e,t)=>{const o=e||t&&"paragraph";return"boolean"==typeof o?n:c[o]?c[o]:n})),this.listenTo(u,"execute",(t=>{const{commandName:o,commandValue:n}=t.source;e.execute(o,n?{value:n}:void 0),e.editing.view.focus()})),u}))}}
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */class b extends t{static get requires(){return[w,k]}static get pluginName(){return"Heading"}}
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */
const _={heading1:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M19 9v10h-2v-8h-2V9h4zM4 8.5h5V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v11.5a1 1 0 0 1-1 1H10a1 1 0 0 1-1-1V11H4v4.5a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v4.5z"/></svg>',heading2:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3 8.5h5V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v11.5a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V11H3v4.5a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1H2a1 1 0 0 1 1 1v4.5zm16.076 8.343V18.5h-6.252c.067-.626.27-1.22.61-1.78.338-.561 1.006-1.305 2.005-2.232.804-.749 1.297-1.257 1.479-1.523.245-.368.368-.732.368-1.092 0-.398-.107-.703-.32-.917-.214-.214-.51-.32-.886-.32-.372 0-.669.111-.889.336-.22.224-.347.596-.38 1.117l-1.778-.178c.106-.982.438-1.686.997-2.114.558-.427 1.257-.64 2.095-.64.918 0 1.64.247 2.164.742.525.495.787 1.11.787 1.847 0 .419-.075.818-.225 1.197-.15.378-.388.775-.714 1.19-.216.275-.605.67-1.168 1.187-.563.516-.92.859-1.07 1.028a3.11 3.11 0 0 0-.365.495h3.542z"/></svg>',heading3:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3 8.5h5V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v11.5a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V11H3v4.5a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1H2a1 1 0 0 1 1 1v4.5zm9.989 7.53 1.726-.209c.055.44.203.777.445 1.01.24.232.533.349.876.349.368 0 .678-.14.93-.42.251-.279.377-.655.377-1.13 0-.448-.12-.803-.362-1.066a1.153 1.153 0 0 0-.882-.393c-.228 0-.501.044-.819.133l.197-1.453c.482.012.85-.092 1.105-.315.253-.222.38-.517.38-.885 0-.313-.093-.563-.279-.75-.186-.185-.434-.278-.743-.278a1.07 1.07 0 0 0-.78.317c-.216.212-.347.52-.394.927l-1.644-.28c.114-.562.287-1.012.517-1.348.231-.337.553-.601.965-.794a3.24 3.24 0 0 1 1.387-.289c.876 0 1.579.28 2.108.838.436.457.653.973.653 1.549 0 .817-.446 1.468-1.339 1.955.533.114.96.37 1.28.768.319.398.478.878.478 1.441 0 .817-.298 1.513-.895 2.088-.596.576-1.339.864-2.228.864-.842 0-1.54-.243-2.094-.727-.555-.485-.876-1.118-.965-1.901z"/></svg>',heading4:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.5h5V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v11.5a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1V11h-5v4.5a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v4.5zm13.55 10v-1.873h-3.81v-1.561l4.037-5.91h1.498v5.904h1.156v1.567h-1.156V18.5H17.05zm0-3.44v-3.18l-2.14 3.18h2.14z"/></svg>',heading5:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.5h5V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v11.5a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1V11h-5v4.5a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v4.5zm9.578 7.607 1.777-.184c.05.402.201.72.45.955a1.223 1.223 0 0 0 1.81-.101c.258-.303.387-.759.387-1.368 0-.572-.128-1-.384-1.286-.256-.285-.59-.428-1-.428-.512 0-.971.226-1.377.679l-1.448-.21.915-4.843h4.716v1.67H15.56l-.28 1.58a2.697 2.697 0 0 1 1.219-.298 2.68 2.68 0 0 1 2.012.863c.55.576.825 1.323.825 2.241a3.36 3.36 0 0 1-.666 2.05c-.605.821-1.445 1.232-2.52 1.232-.86 0-1.56-.23-2.101-.692-.542-.461-.866-1.081-.971-1.86z"/></svg>',heading6:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.5h5V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v11.5a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1V11h-5v4.5a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h.5a1 1 0 0 1 1 1v4.5zm15.595 2.973-1.726.19c-.043-.355-.153-.617-.33-.787-.178-.169-.409-.253-.692-.253-.377 0-.695.169-.956.507-.26.339-.424 1.043-.492 2.114.445-.525.997-.787 1.657-.787.745 0 1.383.284 1.914.85.531.568.797 1.3.797 2.197 0 .952-.28 1.716-.838 2.291-.559.576-1.276.864-2.152.864-.94 0-1.712-.365-2.317-1.095-.605-.73-.908-1.927-.908-3.59 0-1.705.316-2.935.946-3.688.63-.753 1.45-1.13 2.457-1.13.706 0 1.291.198 1.755.594.463.395.758.97.885 1.723zm-4.043 3.891c0 .58.133 1.028.4 1.343.266.315.57.473.914.473.33 0 .605-.13.825-.388.22-.258.33-.68.33-1.27 0-.604-.118-1.047-.355-1.329a1.115 1.115 0 0 0-.89-.422c-.342 0-.632.134-.869.403s-.355.666-.355 1.19z"/></svg>'};class H extends t{init(){x(this.editor).filter((e=>"paragraph"!==e.model)).map((e=>this._createButton(e)))}_createButton(e){const t=this.editor;t.ui.componentFactory.add(e.model,(o=>{const n=new c(o),i=t.commands.get("heading");return n.label=e.title,n.icon=e.icon||_[e.model],n.tooltip=!0,n.isToggleable=!0,n.bind("isEnabled").to(i),n.bind("isOn").to(i,"value",(t=>t==e.model)),n.on("execute",(()=>{t.execute("heading",{value:e.model}),t.editing.view.focus()})),n}))}}
/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */const y=new Set(["paragraph","heading1","heading2","heading3","heading4","heading5","heading6"]);class C extends t{constructor(){super(...arguments),this._bodyPlaceholder=new Map}static get pluginName(){return"Title"}static get requires(){return["Paragraph"]}init(){const e=this.editor,t=e.model;t.schema.register("title",{isBlock:!0,allowIn:"$root"}),t.schema.register("title-content",{isBlock:!0,allowIn:"title",allowAttributes:["alignment"]}),t.schema.extend("$text",{allowIn:"title-content"}),t.schema.addAttributeCheck((e=>{if(e.endsWith("title-content $text"))return!1})),e.editing.mapper.on("modelToViewPosition",P(e.editing.view)),e.data.mapper.on("modelToViewPosition",P(e.editing.view)),e.conversion.for("downcast").elementToElement({model:"title-content",view:"h1"}),e.conversion.for("downcast").add((e=>e.on("insert:title",((e,t,o)=>{o.consumable.consume(t.item,e.name)})))),e.data.upcastDispatcher.on("element:h1",E,{priority:"high"}),e.data.upcastDispatcher.on("element:h2",E,{priority:"high"}),e.data.upcastDispatcher.on("element:h3",E,{priority:"high"}),t.document.registerPostFixer((e=>this._fixTitleContent(e))),t.document.registerPostFixer((e=>this._fixTitleElement(e))),t.document.registerPostFixer((e=>this._fixBodyElement(e))),t.document.registerPostFixer((e=>this._fixExtraParagraph(e))),this._attachPlaceholders(),this._attachTabPressHandling()}getTitle(e={}){const t=e.rootName?e.rootName:void 0,o=this._getTitleElement(t).getChild(0);return this.editor.data.stringify(o,e)}getBody(e={}){const t=this.editor,o=t.data,n=t.model,i=e.rootName?e.rootName:void 0,a=t.model.document.getRoot(i),r=t.editing.view,s=new l(r.document),d=n.createRangeIn(a),c=s.createDocumentFragment(),h=n.createPositionAfter(a.getChild(0)),m=n.createRange(h,n.createPositionAt(a,"end")),g=new Map;for(const e of n.markers){const t=m.getIntersection(e.getRange());t&&g.set(e.name,t)}return o.mapper.clearBindings(),o.mapper.bindElements(a,c),o.downcastDispatcher.convert(d,g,s,e),s.remove(s.createRangeOn(c.getChild(0))),t.data.processor.toData(c)}_getTitleElement(e){const t=this.editor.model.document.getRoot(e);for(const e of t.getChildren())if(T(e))return e}_fixTitleContent(e){let t=!1;for(const o of this.editor.model.document.getRootNames()){const n=this._getTitleElement(o);if(!n||1===n.maxOffset)continue;const i=Array.from(n.getChildren());i.shift();for(const t of i)e.move(e.createRangeOn(t),n,"after"),e.rename(t,"paragraph");t=!0}return t}_fixTitleElement(e){let t=!1;const o=this.editor.model;for(const n of this.editor.model.document.getRoots()){const i=Array.from(n.getChildren()).filter(T),a=i[0],r=n.getChild(0);if(r.is("element","title"))i.length>1&&(B(i,e,o),t=!0);else if(a||y.has(r.name))y.has(r.name)?V(r,e,o):e.move(e.createRangeOn(a),n,0),B(i,e,o),t=!0;else{const o=e.createElement("title");e.insert(o,n),e.insertElement("title-content",o),t=!0}}return t}_fixBodyElement(e){let t=!1;for(const o of this.editor.model.document.getRootNames()){const n=this.editor.model.document.getRoot(o);if(n.childCount<2){const i=e.createElement("paragraph");e.insert(i,n,1),this._bodyPlaceholder.set(o,i),t=!0}}return t}_fixExtraParagraph(e){let t=!1;for(const o of this.editor.model.document.getRootNames()){const n=this.editor.model.document.getRoot(o),i=this._bodyPlaceholder.get(o);A(i,n)&&(this._bodyPlaceholder.delete(o),e.remove(i),t=!0)}return t}_attachPlaceholders(){const e=this.editor,t=e.t,o=e.editing.view,n=e.sourceElement,i=e.config.get("title.placeholder")||t("Type your title"),a=e.config.get("placeholder")||n&&"textarea"===n.tagName.toLowerCase()&&n.getAttribute("placeholder")||t("Type or paste your content here.");e.editing.downcastDispatcher.on("insert:title-content",((e,t,n)=>{const a=n.mapper.toViewElement(t.item);a.placeholder=i,h({view:o,element:a,keepOnFocus:!0})}));const r=new Map;o.document.registerPostFixer((e=>{let t=!1;for(const n of o.document.roots){if(n.isEmpty)continue;const o=n.getChild(1),i=r.get(n.rootName);o!==i&&(i&&(m(e,i),e.removeAttribute("data-placeholder",i)),e.setAttribute("data-placeholder",a,o),r.set(n.rootName,o),t=!0),t=g(o,!0)&&2===n.childCount&&"p"===o.name?!!u(e,o)||t:!!m(e,o)||t}return t}))}_attachTabPressHandling(){const e=this.editor,t=e.model;e.keystrokes.set("TAB",((e,o)=>{t.change((e=>{const n=t.document.selection,i=Array.from(n.getSelectedBlocks());if(1===i.length&&i[0].is("element","title-content")){const t=n.getFirstPosition().root.getChild(1);e.setSelection(t,0),o()}}))})),e.keystrokes.set("SHIFT + TAB",((o,i)=>{t.change((o=>{const a=t.document.selection;if(!a.isCollapsed)return;const r=n(a.getSelectedBlocks()),s=a.getFirstPosition(),d=e.model.document.getRoot(s.root.rootName),c=d.getChild(0);r===d.getChild(1)&&s.isAtStart&&(o.setSelection(c.getChild(0),0),i())}))}))}}function E(e,t,o){const n=t.modelCursor,i=t.viewItem;if(!n.isAtStart||!n.parent.is("element","$root"))return;if(!o.consumable.consume(i,{name:!0}))return;const a=o.writer,r=a.createElement("title"),s=a.createElement("title-content");a.append(s,r),a.insert(r,n),o.convertChildren(i,s),o.updateConversionResult(r,t)}function P(e){return(t,o)=>{const n=o.modelPosition.parent;if(!n.is("element","title"))return;const i=n.parent,a=o.mapper.toViewElement(i);o.viewPosition=e.createPositionAt(a,0),t.stop()}}function T(e){return e.is("element","title")}function V(e,t,o){const n=t.createElement("title");t.insert(n,e,"before"),t.insert(e,n,0),t.rename(e,"title-content"),o.schema.removeDisallowedAttributes([e],t)}function B(e,t,o){let n=!1;for(const i of e)0!==i.index&&(N(i,t,o),n=!0);return n}function N(e,t,o){const n=e.getChild(0);n.isEmpty?t.remove(e):(t.move(t.createRangeOn(n),e,"before"),t.rename(n,"paragraph"),t.remove(e),o.schema.removeDisallowedAttributes([n],t))}function A(e,t){return!(!e||!e.is("element","paragraph")||e.childCount)&&!(t.childCount<=2||t.getChild(t.childCount-1)!==e)}export{b as Heading,H as HeadingButtonsUI,w as HeadingEditing,k as HeadingUI,C as Title};