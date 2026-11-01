import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const workspace = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const captureScript = path.join(workspace, 'scripts', 'capture-scene.mjs');
const cases = [
  { id: 'compact-first', scene: 'onboarding', width: 844, height: 390, layout: 'compact', gate: 'inactive' },
  { id: 'standard-ready', scene: 'onboarding-ready', width: 1600, height: 900, layout: 'standard', gate: 'inactive' },
  { id: 'wide-second', scene: 'onboarding-progress', width: 2560, height: 1080, layout: 'wide', gate: 'inactive' },
  { id: 'portrait-first', scene: 'onboarding', width: 390, height: 844, layout: 'compact', gate: 'active', mobile: true },
  { id: 'camp-returning', scene: 'camp', width: 1600, height: 900, layout: 'standard', gate: 'inactive' },
];

function usage() {
  console.log('Usage: npm run capture:onboarding:matrix -- [--output-dir <path>] [--base-url <url>] [--port <port>] [--list]');
}

function parseArgs(argv) {
  const options = { outputDir: path.join(workspace, 'artifacts', 'screenshots', 'onboarding-matrix'), baseUrl: '', port: '', list: false };
  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === '--help') { usage(); process.exit(0); }
    if (arg === '--list') { options.list = true; continue; }
    if (!['--output-dir', '--base-url', '--port'].includes(arg) || !argv[index + 1] || argv[index + 1].startsWith('--')) {
      throw new Error(`Unknown or incomplete option: ${arg}`);
    }
    const value = argv[++index];
    if (arg === '--output-dir') options.outputDir = path.resolve(workspace, value);
    if (arg === '--base-url') options.baseUrl = value;
    if (arg === '--port') {
      if (!/^[1-9][0-9]{0,4}$/.test(value) || Number(value) > 65535) throw new Error('Port must be between 1 and 65535.');
      options.port = value;
    }
  }
  return options;
}

function captureArgs(testCase, options) {
  const args = [captureScript, '--scene', testCase.scene, '--width', String(testCase.width), '--height', String(testCase.height),
    '--expect-layout', testCase.layout, '--expect-gate', testCase.gate,
    '--output', path.join(options.outputDir, `${testCase.id}.png`)];
  if (testCase.mobile) args.push('--mobile');
  if (options.baseUrl) args.push('--base-url', options.baseUrl);
  if (options.port) args.push('--port', options.port);
  return args;
}

function capture(testCase, options) {
  const started = Date.now();
  const args = captureArgs(testCase, options);
  return new Promise((resolve) => {
    const child = spawn(process.execPath, args, { cwd: workspace, stdio: ['ignore', 'pipe', 'pipe'] });
    let output = '';
    child.stdout.on('data', (chunk) => { output += chunk.toString(); });
    child.stderr.on('data', (chunk) => { output += chunk.toString(); });
    child.on('error', (error) => resolve({ exitCode: 1, output: error.message, durationMs: Date.now() - started }));
    child.on('close', (exitCode) => resolve({ exitCode: exitCode ?? 1, output, durationMs: Date.now() - started }));
  });
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  if (options.list) {
    for (const testCase of cases) {
      console.log(`${testCase.id}: ${testCase.scene} ${testCase.width}x${testCase.height}, ${testCase.layout}, gate ${testCase.gate}${testCase.mobile ? ', mobile touch' : ''}`);
    }
    return;
  }
  await mkdir(options.outputDir, { recursive: true });
  const results = [];
  for (const testCase of cases) {
    const result = await capture(testCase, options);
    const passed = result.exitCode === 0;
    results.push({ ...testCase, status: passed ? 'pass' : 'fail', duration_ms: result.durationMs,
      output: path.relative(workspace, path.join(options.outputDir, `${testCase.id}.png`)).replaceAll('\\', '/'),
      ...(passed ? {} : { diagnostic: result.output.trim().slice(-4000) }) });
    console.log(`${passed ? 'PASS' : 'FAIL'}  ${testCase.id} · ${(result.durationMs / 1000).toFixed(1)}s`);
    if (!passed) console.error(result.output.trim().slice(-4000));
  }
  const summary = { schema_version: 1, generated_at: new Date().toISOString(),
    overall_status: results.every((result) => result.status === 'pass') ? 'pass' : 'fail', captures: results };
  const summaryPath = path.join(options.outputDir, 'summary.json');
  await writeFile(summaryPath, `${JSON.stringify(summary, null, 2)}\n`);
  console.log(`Summary: ${path.relative(workspace, summaryPath).replaceAll('\\', '/')}`);
  if (summary.overall_status === 'fail') process.exitCode = 1;
}

main().catch((error) => { console.error(error.message); process.exitCode = 1; });
