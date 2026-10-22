from pathlib import Path
import hashlib,json,zipfile,re
audit=Path(__file__).resolve().parent
stage=audit/'commander';output=audit/'commander-cookie-storage-1.5.14.zip'
files=sorted(p for p in stage.rglob('*') if p.is_file())
assert len(files)==4
attachment=Path(r'C:\Users\Angelo\.codex\attachments\26a1103b-7f21-40da-8224-f231c2a4e7ec\Testo incollato.txt')
secret=re.search(r'```\s*\n([A-Za-z0-9]{35,80})\s*\n```',attachment.read_text(encoding='utf8')).group(1).encode()
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
    for path in files:
        assert secret not in path.read_bytes()
        archive.write(path,path.relative_to(stage).as_posix())
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    for path in files: assert archive.read(path.relative_to(stage).as_posix())==path.read_bytes()
result={'files':len(files),'bytes':output.stat().st_size,'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'source_parity':True,'secrets_absent':True}
(audit/'commander-package-results.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result))
