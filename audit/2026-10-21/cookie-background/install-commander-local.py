from pathlib import Path
import json,hashlib,shutil
audit=Path(__file__).resolve().parent;stage=audit/'commander'
target=Path(r'C:\Users\Angelo\Documents\GitHub\marrison-commander\commander').resolve()
baseline=json.loads((audit/'commander-baseline.json').read_text())
files=sorted(p for p in stage.rglob('*') if p.is_file())
for source in files:
 relative=source.relative_to(stage);destination=(target/relative).resolve();assert destination.is_relative_to(target)
 if relative.as_posix() in baseline: assert hashlib.sha256(destination.read_bytes()).hexdigest()==baseline[relative.as_posix()]
 else: assert not destination.exists() or destination.read_bytes()==source.read_bytes()
for source in files:
 destination=target/source.relative_to(stage);destination.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,destination);assert destination.read_bytes()==source.read_bytes()
print('Commander local: four reviewed files updated; existing configuration untouched')
