import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import * as icons from '@animateicons/react/lucide';
import { build } from 'esbuild';

const dir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(dir, '../..');
const mapping = JSON.parse(fs.readFileSync(path.join(dir, 'mapping.json'), 'utf8'));
const names = [...new Set(Object.values(mapping))];
const svgByName = {};
for (const name of names) {
  if (!icons[name]) throw new Error(`Unknown AnimateIcons export: ${name}`);
  const markup = renderToStaticMarkup(React.createElement(icons[name], {size:24,isAnimated:false}));
  const svg = markup.match(/<svg\b[\s\S]*?<\/svg>/)?.[0];
  if (!svg || /<script|<foreignObject|\bhref=|\bonload=/i.test(svg)) throw new Error(`Unsafe SVG: ${name}`);
  svgByName[name] = svg;
}
let css = '/* Generated from @animateicons/react 0.5.0. MIT. Rebuild: npm ci && npm run build in tools/animateicons. */\n';
for (const name of names) {
  const selectors = Object.keys(mapping).filter(a=>mapping[a]===name).map(a=>`.bi-${a}`);
  if (name===mapping.list) selectors.push('.navbar-toggler-icon');
  if (name===mapping['x-lg']) selectors.push('.btn-close');
  const encoded = encodeURIComponent(svgByName[name]).replace(/[!'()*]/g,c=>'%'+c.charCodeAt(0).toString(16).toUpperCase());
  css += `${selectors.join(',\n')} { --icon-regular: url("data:image/svg+xml,${encoded}"); }\n`;
}
fs.writeFileSync(path.join(root,'assets/icons/animateicons.css'),css);
fs.writeFileSync(path.join(root,'assets/icons/animateicons-manifest.json'),JSON.stringify({package:'@animateicons/react',version:'0.5.0',mapping,svgByName},null,2)+'\n');
const selected = `import { ${names.join(',')} } from '@animateicons/react/lucide';\nexport default {${Object.entries(mapping).map(([a,n])=>`${JSON.stringify(a)}:${n}`).join(',')}};`;
await build({
  entryPoints:[path.join(dir,'runtime.jsx')],outfile:path.join(root,'assets/js/animateicons.js'),
  bundle:true,minify:true,format:'iife',target:['es2020'],legalComments:'linked',
  define:{'process.env.NODE_ENV':'"production"'},
  plugins:[{name:'selected-icons',setup(b){
    b.onResolve({filter:/^selected-icons$/},()=>({path:'selected-icons',namespace:'selected'}));
    b.onLoad({filter:/.*/,namespace:'selected'},()=>({contents:selected,resolveDir:dir,loader:'js'}));
  }}]
});
const license = fs.readFileSync(path.join(dir,'node_modules/@animateicons/react/LICENSE'),'utf8');
fs.writeFileSync(path.join(root,'assets/icons/ANIMATEICONS-LICENSE'),license);
const dependencies = ['react','react-dom','scheduler'];
fs.writeFileSync(path.join(root,'assets/icons/ANIMATEICONS-DEPENDENCIES-LICENSE'),dependencies.map(name=>
  `${name}\n\n${fs.readFileSync(path.join(dir,'node_modules',name,'LICENSE'),'utf8')}`
).join('\n\n'));
console.log(`Built ${Object.keys(mapping).length} aliases / ${names.length} official components.`);
