import hashlib
import json
import zipfile
from pathlib import Path

evidence = Path(__file__).resolve().parent
repo = evidence.parents[2]
plugin = repo / "marrison-addon"
installed = Path(r"C:\Users\Angelo\Local Sites\tesy\app\public\wp-content\plugins\marrison-addon")


def read_json(name):
    return json.loads((evidence / name).read_text(encoding="utf-8-sig"))


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


contracts = read_json("contract-tests.json")
syntax = read_json("syntax-checks.json")
isolation = read_json("isolation/summary.json")
package = read_json("package-results.json")
cleanup = read_json("cleanup.json")
records = read_json("record-cleanliness.json")
assert len(contracts) == 14 and all(item["exit"] == 0 for item in contracts)
assert all("Warning:" not in item["output"] for item in contracts)
assert len(syntax) == 63 and all(item["exit"] == 0 for item in syntax)
assert len(isolation) == 25 and sum(item["mode"] == "isolation" for item in isolation) == 21
assert all(item["errors"] == 0 for item in isolation)
assert not records["remaining_test_records"] and not records["errors"]
assert cleanup["removed"] == 11
assert all(item["removed"] and not Path(item["destination"]).exists() for item in cleanup["files"])

files = sorted(path for path in plugin.rglob("*") if path.is_file() and path.name != "AGENTS.md")
assert len(files) == 78
assert all(sha256(path) == sha256(installed / path.relative_to(plugin)) for path in files)
archive_path = Path(package["path"])
assert sha256(archive_path) == package["sha256"]
assert sha256(repo / "marrison-addon.zip") == package["existing_zip_sha256"]
with zipfile.ZipFile(archive_path) as archive:
    assert archive.testzip() is None
    assert len(archive.infolist()) == 78
    assert "marrison-addon/marrison-addon.php" in archive.namelist()
    for path in files:
        name = "marrison-addon/" + path.relative_to(plugin).as_posix()
        assert archive.read(name) == path.read_bytes()

report = (evidence / "REPORT.md").read_text(encoding="utf-8")
assert (evidence / "../../../marrison-addon-1.3.44.zip").resolve() == archive_path.resolve()
assert sum(line.startswith("| ") and line.split("|")[1].strip().isdigit() for line in report.splitlines()) == 21
result = {
    "version": "1.3.44",
    "confirmed_findings_documented": 21,
    "contract_suites_passed": 14,
    "syntax_checks_passed": 63,
    "isolated_modules": 21,
    "total_isolation_and_dependency_runs": 25,
    "installed_files_equal_source": 78,
    "archive_members_equal_source": 78,
    "fixtures_removed": 11,
    "temporary_records_remaining": 0,
    "existing_zip_unchanged": True,
    "package_sha256": package["sha256"],
}
(evidence / "delivery-verification.json").write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
print(json.dumps(result, indent=2))
