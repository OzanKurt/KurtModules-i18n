// Builds the publishable UI assets into resources/dist:
//   - compiles + minifies Tailwind CSS (resources/css/app.css -> dist/app.css)
//   - copies the vanilla-JS app (resources/js/app.js -> dist/app.js)
// Run with `npm run build`. CI then asserts resources/dist has no git diff.

import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdirSync } from 'node:fs';

mkdirSync('resources/dist', { recursive: true });

execFileSync(
    'npx',
    ['@tailwindcss/cli', '-i', 'resources/css/app.css', '-o', 'resources/dist/app.css', '--minify'],
    { stdio: 'inherit', shell: true },
);

copyFileSync('resources/js/app.js', 'resources/dist/app.js');

console.log('Built resources/dist/{app.css,app.js}');
