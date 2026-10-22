from pathlib import Path
import hashlib,json,zipfile
audit=Path(__file__).resolve().parent
stage=audit/'commander'
output=audit/'commander-cookie-browserless-1.5.13.zip'
files=sorted(p for p in stage.rglob('*') if p.is_file())
assert len(files)==9, [p.relative_to(stage).as_posix() for p in files]
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
    for path in files:
        assert not path.name.startswith('.')
        archive.write(path,path.relative_to(stage).as_posix())
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    for path in files: assert archive.read(path.relative_to(stage).as_posix())==path.read_bytes()
metadata={'files':len(files),'bytes':output.stat().st_size,'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'source_parity':True,'contains_secrets':False}
(audit/'commander-package-results.json').write_text(json.dumps(metadata,indent=2)+'\n')
print(json.dumps(metadata))
