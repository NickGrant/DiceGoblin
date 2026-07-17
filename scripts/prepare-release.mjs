import { access, cp, mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDir, '..');
const deployRoot = path.join(repoRoot, 'artifacts', 'deploy');

async function exists(targetPath) {
  try {
    await access(targetPath);
    return true;
  } catch {
    return false;
  }
}

async function resetDirectory(targetPath) {
  await rm(targetPath, { recursive: true, force: true });
  await mkdir(targetPath, { recursive: true });
}

async function copyRequired(sourcePath, destinationPath) {
  if (!(await exists(sourcePath))) {
    throw new Error(`Required release path does not exist: ${path.relative(repoRoot, sourcePath)}`);
  }

  await cp(sourcePath, destinationPath, { recursive: true });
}

async function copyOptional(sourcePath, destinationPath) {
  if (await exists(sourcePath)) {
    await cp(sourcePath, destinationPath, { recursive: true });
  }
}

function requireEnvironmentVariable(name) {
  const value = process.env[name]?.trim();
  if (!value) {
    throw new Error(`Required environment variable is missing: ${name}`);
  }
  return value;
}

async function prepareFrontend() {
  const sourceDir = path.join(repoRoot, 'frontend', 'dist', 'browser');
  const destinationDir = path.join(deployRoot, 'frontend');
  const apiBaseUrl = requireEnvironmentVariable('DICE_GOBLIN_RELEASE_API_URL').replace(/\/$/, '');

  await resetDirectory(destinationDir);
  await copyRequired(sourceDir, destinationDir);

  const runtimeConfig = `window.__DICE_GOBLIN_CONFIG__ = ${JSON.stringify(
    {
      apiBaseUrl,
      enableDevPanel: false,
    },
    null,
    2,
  )};\n`;

  await writeFile(path.join(destinationDir, 'runtime-config.js'), runtimeConfig, 'utf8');
  console.log(`Prepared frontend release at ${path.relative(repoRoot, destinationDir)}`);
}

async function prepareBackend() {
  const backendDir = path.join(repoRoot, 'backend');
  const destinationDir = path.join(deployRoot, 'backend');

  await resetDirectory(destinationDir);

  await copyRequired(path.join(backendDir, 'public'), path.join(destinationDir, 'public'));
  await copyRequired(path.join(backendDir, 'src'), path.join(destinationDir, 'src'));
  await copyOptional(path.join(backendDir, 'vendor'), path.join(destinationDir, 'vendor'));
  await copyOptional(path.join(backendDir, 'composer.json'), path.join(destinationDir, 'composer.json'));
  await copyOptional(path.join(backendDir, 'composer.lock'), path.join(destinationDir, 'composer.lock'));

  console.log(`Prepared backend release at ${path.relative(repoRoot, destinationDir)}`);
}

const component = process.argv[2];

try {
  switch (component) {
    case 'frontend':
      await prepareFrontend();
      break;
    case 'backend':
      await prepareBackend();
      break;
    case 'all':
      await prepareFrontend();
      await prepareBackend();
      break;
    default:
      throw new Error('Usage: node scripts/prepare-release.mjs <frontend|backend|all>');
  }
} catch (error) {
  console.error(error instanceof Error ? error.message : error);
  process.exitCode = 1;
}
