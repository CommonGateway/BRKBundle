"use strict";(self.webpackChunkproduct_website_template=self.webpackChunkproduct_website_template||[]).push([[451],{1965:function(e,t,n){n.d(t,{C:function(){return R}});var r=n(1469),s=n.n(r),o="ParsedHTML-module--container--4efc0",c=n(7311),l=n(7294);const a=l.createContext({}),i=!0;function d({baseColor:e,highlightColor:t,width:n,height:r,borderRadius:s,circle:o,direction:c,duration:l,enableAnimation:a=i}){const d={};return"rtl"===c&&(d["--animation-direction"]="reverse"),"number"==typeof l&&(d["--animation-duration"]=`${l}s`),a||(d["--pseudo-element-display"]="none"),"string"!=typeof n&&"number"!=typeof n||(d.width=n),"string"!=typeof r&&"number"!=typeof r||(d.height=r),"string"!=typeof s&&"number"!=typeof s||(d.borderRadius=s),o&&(d.borderRadius="50%"),void 0!==e&&(d["--base-color"]=e),void 0!==t&&(d["--highlight-color"]=t),d}function u({count:e=1,wrapper:t,className:n,containerClassName:r,containerTestId:s,circle:o=!1,style:c,...u}){var h,m,f;const p=l.useContext(a),b={...u};for(const[l,a]of Object.entries(u))void 0===a&&delete b[l];const g={...p,...b,circle:o},x={...c,...d(g)};let v="react-loading-skeleton";n&&(v+=` ${n}`);const y=null!==(h=g.inline)&&void 0!==h&&h,j=[],w=Math.ceil(e);for(let a=0;a<w;a++){let t=x;if(w>e&&a===w-1){const n=null!==(m=t.width)&&void 0!==m?m:"100%",r=e%1,s="number"==typeof n?n*r:`calc(${n} * ${r})`;t={...t,width:s}}const n=l.createElement("span",{className:v,style:t,key:a},"‌");y?j.push(n):j.push(l.createElement(l.Fragment,{key:a},n,l.createElement("br",null)))}return l.createElement("span",{className:r,"data-testid":s,"aria-live":"polite","aria-busy":null!==(f=g.enableAnimation)&&void 0!==f?f:i},t?j.map(((e,n)=>l.createElement(t,{key:n},e))):j)}var h=n(5663),m=n(7814),f=n(9417),p=n(512),b=n(5893);var g=n(1700),x=n.n(g),v=n(4160);const y=(e,t,n,r)=>{if(e.href.includes("/"))if(e.href.includes("/")){const n=e.href.split("/").reverse()[1],s=r.some((e=>e.location.substring(e.location.lastIndexOf("/")+1)===n));if(s&&(0,v.c4)("/"+x()(n)+"/"+t),!s){const t=e.href.startsWith("/")?e.href:"/"+e.href;open("https://github.com/CommonGateway/BRKBundle/blob/master"+t)}}else;else{const e=x()(n.split("/").reverse()[1]);(0,v.c4)("/"+e+"/"+t)}},j=e=>{var t,n,r;const s=null!==(t=e.id)&&void 0!==t?t:e.href.replace("#","user-content-"),o=document.getElementById(s),c=null!==(n=null===(r=document.getElementById("header"))||void 0===r?void 0:r.clientHeight)&&void 0!==n?n:100;o&&window.scrollTo({top:o.offsetTop-(c+24),behavior:"smooth"})};var w="getList-module--list--5f814";var N=n(1562);var C=n(2856);var k=n(1072);const E=e=>{const{directories:t}=(0,C.O)(),{t:n}=(0,k.$G)(),r={scrollLeftButton:n("Left scroll button"),scrollRightButton:n("Right scroll button")},s={replace:n=>{var o;let{attribs:l,parent:a,children:i,name:d}=n;if(!l)return;const u=(0,c.e_)(l);return!l||"h1"!==d&&"h2"!==d&&"h3"!==d&&"h4"!==d?l&&"p"===d?((e,t,n)=>(0,b.jsx)(h.nv,{...e,children:(0,c.du)(t,n)}))(u,i,s):l&&"a"===d?((e,t,n,r,s)=>{const o={...e,onClick:t=>{t.preventDefault();const n=x()(e.href.substring(e.href.lastIndexOf("/")+1).replace(".md",""));e.href?"anchor"!==e.className&&"#"!==Array.from(e.href)[0]?e.href.includes("://")?e.href.includes("://")&&open(e.href):y(e,n,s,r):j(e):(0,v.c4)("#")}};return(0,b.jsx)(h.rU,{...o,children:(0,c.du)(t,n)})})(u,i,s,t,e):!l||"ol"!==d&&"ul"!==d?l&&"li"===d?((e,t,n,r)=>{switch(t.name){case"ol":return(0,b.jsx)(h.Ux,{...e,children:(0,c.du)(n,r)});case"ul":return(0,b.jsx)(h.AS,{...e,children:(0,c.du)(n,r)})}})(u,a,i,s):l&&"img"===d?((e,t)=>{let n=e.src;if(!e.src.includes("https://")){const t="https://github.com/CommonGateway/BRKBundle",r=null==t?void 0:t.replace("https://github.com/","");n="https://raw.githubusercontent.com/"+r+"/master/docs/features/"+e.src}let r=e.alt;e.alt||(r=e.title),e.alt||e.title||(r=e.src);const s={...e,src:n,alt:r,href:"",onClick:e=>{"a"===t.name&&e.preventDefault(),"a"!==t.name&&(e.stopPropagation(),open(n))}};return(0,b.jsx)(h.Ee,{...s})})(u,a):l&&"blockquote"===d?((e,t)=>(0,b.jsx)(h.bZ,{children:(0,c.du)(e,t)}))(i,s):l&&"div"===d&&null!==(o=l.class)&&void 0!==o&&o.includes("markdown-alert")?((e,t,n)=>{switch(!0){case n.includes("note")||n.includes("info"):return(0,b.jsx)(h.bZ,{className:"getAlert-module--info--6faee",type:"info",children:(0,c.du)(e,t)});case n.includes("error"):return(0,b.jsx)(h.bZ,{className:"getAlert-module--error--a7ff3",type:"error",children:(0,c.du)(e,t)});case n.includes("warning"):return(0,b.jsx)(h.bZ,{className:"getAlert-module--warning--16e7a",type:"warning",children:(0,c.du)(e,t)});case n.includes("succes")||n.includes("ok"):return(0,b.jsx)(h.bZ,{className:"getAlert-module--ok--75642",type:"ok",children:(0,c.du)(e,t)});default:return(0,b.jsx)(h.bZ,{type:"info",children:(0,c.du)(e,t)})}})(i,s,l.class):l&&"table"===d?((e,t,n,r)=>(0,b.jsx)(N.QZ,{ariaLabels:r,children:(0,b.jsx)(h.iA,{className:"getTable-module--table--b07c6",...e,children:(0,c.du)(t,n)})}))(u,i,s,r):l&&"tr"===d?((e,t,n)=>(0,b.jsx)(h.SC,{className:"getTableRow-module--tableRow--43fa5",...e,children:(0,c.du)(t,n)}))(u,i,s):l&&"thead"===d?((e,t,n)=>(0,b.jsx)(h.xD,{className:"getTableHeader-module--tableHeader--94fa3",...e,children:(0,c.du)(t,n)}))(u,i,s):l&&"th"===d?((e,t,n)=>(0,b.jsx)(h.xs,{...e,children:(0,c.du)(t,n)}))(u,i,s):l&&"tbody"===d?((e,t,n)=>(0,b.jsx)(h.RM,{className:"getTableBody-module--tableBody--7e02c",...e,children:(0,c.du)(t,n)}))(u,i,s):l&&"td"===d?((e,t,n)=>(0,b.jsx)(h.pj,{className:"getTableCell-module--tableCell--c1c5e",...e,children:(0,c.du)(t,n)}))(u,i,s):l&&"svg"===d?((e,t,n)=>e.className.includes("octicon octicon-link")?(0,b.jsx)(b.Fragment,{}):(0,b.jsx)("svg",{...e,children:(0,c.du)(t,n)}))(u,i,s):!l||"code"!==d&&"pre"!==d?void 0:((e,t,n,r,s)=>{switch(e){case"code":return(0,b.jsx)(h.EK,{...t,children:(0,c.du)(n,r)});case"pre":return(0,b.jsx)(h.dn,{...t,children:(0,b.jsx)(N.QZ,{ariaLabels:s,children:(0,c.du)(n,r)})})}})(d,u,i,s,r):((e,t,n,r)=>{switch(e){case"ol":return(0,b.jsx)(h.GS,{className:w,...t,children:(0,c.du)(n,r)});case"ul":return(0,b.jsx)(h.QI,{className:w,...t,children:(0,c.du)(n,r)})}})(d,u,i,s):((e,t,n,r)=>{switch(e){case"h1":return(0,b.jsx)(h.nL,{...t,children:(0,c.du)(n,r)});case"h2":return(0,b.jsx)(h.XJ,{...t,children:(0,c.du)(n,r)});case"h3":return(0,b.jsx)(h.aC,{...t,children:(0,c.du)(n,r)});case"h4":return(0,b.jsx)(h.k8,{...t,children:(0,c.du)(n,r)})}})(d,u,i,s)}};return{options:s}},R=e=>{let{contentQuery:t,location:n,layoutClassName:r}=e;const{options:l}=E(n);return t.isLoading?(0,b.jsx)("div",{className:o,children:(0,b.jsx)(u,{height:"200px"})}):t.isError?(0,b.jsx)("div",{className:o,children:(0,b.jsx)(h.bZ,{icon:(0,b.jsx)(m.G,{icon:f.e7M}),type:"error",children:"Oops, something went wrong retrieving the .md file from GitHub."})}):s()(t.data)?void 0:(0,b.jsx)("div",{className:(0,p.Z)(o,r&&r),children:(0,c.ZP)(t.data,l)})}},1529:function(e,t,n){n.d(t,{H:function(){return c}});var r=n(7294),s=n(8767),o=n(7177);const c=()=>{const e=r.useContext(o.Z);return{getContent:t=>(0,s.useQuery)(["contents",t],(()=>null==e?void 0:e.GitHub.getContent(t)),{onError:e=>{console.warn(e.message)}}),getDirectoryItems:t=>(0,s.useQuery)(["directory-items",t],(()=>null==e?void 0:e.GitHub.getDirectoryItems(t)),{onError:e=>{console.warn(e.message)}})}}},2856:function(e,t,n){n.d(t,{O:function(){return s}});var r=n(7294);const s=()=>{const[e,t]=r.useState([]);r.useEffect((()=>{const e='[{"name": "Documentatie", "location": "/docs"}]';try{const n=JSON.parse(e);t(n)}catch{console.warn("Something went wrong parsing the GitHub directories.")}}),[]);const n=e=>e.replace("-"," ");return{directories:e,getSlugFromName:e=>e.replace(" ","-"),getDirectoryReadMeLocation:t=>{const r=e.find((e=>e.name===n(t)));return r?r.location+"/README.md":""},getDetailMdLocation:(t,r)=>{const s=e.find((e=>e.name===n(t)));return s?s.location+"/"+n(r)+".md":""}}}}}]);
//# sourceMappingURL=82bdb13b4e3c759e34832e6010b22c19d95411b1-aa87445c2052eed3f7b4.js.map