import {build} from 'rolldown';import {spawnSync} from 'node:child_process';import {mkdirSync} from 'node:fs';
mkdirSync('storage/app/private/phase2-tests',{recursive:true});
await build({input:'scripts/recovery-contracts.test.cjs',platform:'node',output:{file:'storage/app/private/phase2-tests/recovery-contracts.cjs',format:'cjs'}});
const result=spawnSync(process.execPath,['--test','--test-isolation=none','storage/app/private/phase2-tests/recovery-contracts.cjs'],{stdio:'inherit'});process.exitCode=result.status??1;
