import json
import sys

from mcp_probe import McpClient, tool_payload

DRAFT_PAGE_ID = 179791
READ = "wp_ability_uncode_builder_"
EDIT = "wp_ability_uncode_builder_edit_"


def connect(token_file):
    with open(token_file, encoding="utf-8") as handle:
        client = McpClient(json.load(handle)["access_token"])
    client.request("initialize", {
        "protocolVersion": "2025-06-18",
        "capabilities": {},
        "clientInfo": {"name": "uncode-builder-write-probe", "version": "0.2"},
    })
    client.request("notifications/initialized", notification=True)
    return client


def call(client, name, arguments):
    status, result = client.request("tools/call", {"name": name, "arguments": arguments})
    is_error = isinstance(result, dict) and (result.get("error") or result.get("result", {}).get("isError"))
    return status, tool_payload(result) if isinstance(result, dict) and "result" in result else result, bool(is_error)


def report(label, ok, detail=""):
    text = detail if isinstance(detail, str) else json.dumps(detail, ensure_ascii=False)
    print(("OK    " if ok else "FALLO ") + label + (" -> " + text[:400] if text else ""))


def read_tree(client):
    _, tree, _ = call(client, READ + "get_page_tree", {"post_id": DRAFT_PAGE_ID, "decode": False})
    return tree


def main(read_token_file, write_token_file):
    reader = connect(read_token_file)
    _, listing = reader.request("tools/list")
    names = {tool["name"] for tool in listing.get("result", {}).get("tools", [])}
    edit_tools = sorted(name for name in names if name.startswith(EDIT))
    report("herramientas de edición publicadas", len(edit_tools) == 5, edit_tools)

    tree = read_tree(reader)
    report("token de lectura lee el borrador", isinstance(tree, dict) and tree.get("post", {}).get("status") == "draft")
    status, denied, is_error = call(reader, EDIT + "insert_elements", {
        "post_id": DRAFT_PAGE_ID, "version": tree["version"], "dry_run": True,
        "elements": [{"tag": "vc_row", "children": [{"tag": "vc_column"}]}],
    })
    report("token de lectura NO puede escribir", status == 403 or is_error, f"{status} {str(denied)[:160]}")

    writer = connect(write_token_file)
    tree = read_tree(writer)
    rows_before = len(tree["tree"])
    section = {
        "tag": "vc_row",
        "children": [{
            "tag": "vc_column", "attributes": {"width": "1/1"},
            "children": [
                {"tag": "vc_custom_heading", "attributes": {"heading_semantic": "h3"}, "content": "Insertada vía MCP"},
                {"tag": "vc_column_text", "content": "<p>Fila creada por un agente a través de MCP + OAuth.</p>"},
            ],
        }],
    }

    status, preview, is_error = call(writer, EDIT + "insert_elements", {
        "post_id": DRAFT_PAGE_ID, "version": tree["version"], "dry_run": True, "elements": [section],
    })
    report("insert dry_run por MCP", not is_error and preview.get("dry_run") and not preview.get("saved"), preview)

    status, inserted, is_error = call(writer, EDIT + "insert_elements", {
        "post_id": DRAFT_PAGE_ID, "version": tree["version"], "elements": [section],
    })
    report("insert real por MCP", not is_error and inserted.get("saved"), inserted if is_error else inserted.get("elements"))
    if is_error:
        return
    row = inserted["elements"][0]
    heading_path = row["path"] + ".0.0"

    status, updated, is_error = call(writer, EDIT + "update_element", {
        "post_id": DRAFT_PAGE_ID, "version": inserted["version"], "path": heading_path,
        "expect_tag": "vc_custom_heading", "content": "Insertada y editada vía MCP",
    })
    report("update por MCP", not is_error and updated.get("saved"), updated if is_error else updated.get("elements"))

    status, stale, is_error = call(writer, EDIT + "update_element", {
        "post_id": DRAFT_PAGE_ID, "version": inserted["version"], "path": heading_path, "content": "x",
    })
    report("versión antigua rechazada por MCP", is_error, str(stale)[:160])

    status, removed, is_error = call(writer, EDIT + "remove_element", {
        "post_id": DRAFT_PAGE_ID, "version": updated["version"], "id": row["id"], "expect_tag": "vc_row",
    })
    after_remove = read_tree(writer)
    report("remove por MCP", not is_error and len(after_remove["tree"]) == rows_before, removed if is_error else f"filas: {len(after_remove['tree'])}")

    status, restored, is_error = call(writer, EDIT + "restore_snapshot", {
        "post_id": DRAFT_PAGE_ID, "version": after_remove["version"],
    })
    final = read_tree(writer)
    headings = [node.get("content") for row_node in final["tree"] for column in row_node.get("children", []) for node in column.get("children", []) if node.get("tag") == "vc_custom_heading"]
    report("restore por MCP recupera la fila", not is_error and "Insertada y editada vía MCP" in headings, restored if is_error else restored.get("warnings"))
    print("estado final:", final["post"]["status"], "| filas:", len(final["tree"]), "| snapshots:", len(final["snapshots"]))


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
