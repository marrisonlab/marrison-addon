"""Copy the reviewed patch into the explicitly requested local Commander repository."""
from pathlib import Path
import hashlib,json,shutil
audit=Path(__file__).resolve().parent
stage=audit/'commander'
destination=Path(r'C:\Users\Angelo\Documents\GitHub\marrison-commander\commander').resolve()
baseline=json.loads((audit/'commander-baseline.json').read_text())
files=sorted(p for p in stage.rglob('*') if p.is_file())
for source in files:
    relative=source.relative_to(stage)
    target=(destination/relative).resolve()
    assert target.is_relative_to(destination)
    if relative.as_posix() in baseline:
        assert hashlib.sha256(target.read_bytes()).hexdigest()==baseline[relative.as_posix()], 'Commander changed while preparing patch: '+relative.as_posix()
    else:
        assert not target.exists() or target.read_bytes()==source.read_bytes(), 'Existing file must not be overwritten: '+relative.as_posix()
for source in files:
    target=destination/source.relative_to(stage)
    target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copyfile(source,target)
    assert target.read_bytes()==source.read_bytes()
print(json.dumps({'local_commander_files_updated':len(files),'all_files_match_reviewed_package':True}))
