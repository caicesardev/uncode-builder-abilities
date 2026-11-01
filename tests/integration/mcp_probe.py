import json
import os
import sys
import urllib.error
import urllib.request

ENDPOINT = os.environ["UBA_MCP_ENDPOINT"]
DRAFT_PAGE_ID = int(os.environ["UBA_TEST_PAGE_ID"])


class McpClient:
    def __init__(self, token):
        self.token = token
        self.session_id = None
        self.next_id = 1

    def request(self, method, params=None, notification=False):
        body = {"jsonrpc": "2.0", "method": method}
        if params is not None:
            body["params"] = params
        if not notification:
            body["id"] = self.next_id
            self.next_id += 1
        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json, text/event-stream",
            "Authorization": f"Bearer {self.token}",
            "User-Agent": "Mozilla/5.0 uncode-builder-probe",
        }
        if self.session_id:
            headers["Mcp-Session-Id"] = self.session_id
        req = urllib.request.Request(ENDPOINT, data=json.dumps(body).encode(), headers=headers, method="POST")
        try:
            with urllib.request.urlopen(req, timeout=60) as response:
                self.session_id = response.headers.get("Mcp-Session-Id") or self.session_id
                raw = response.read().decode()
                status = response.status
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode()[:400]
        return status, parse_body(raw)


def parse_body(raw):
    if not raw.strip():
        return None
    if raw.lstrip().startswith("{"):
        return json.loads(raw)
    events = [line[5:].strip() for line in raw.splitlines() if line.startswith("data:")]
    return json.loads(events[-1]) if events else raw


def tool_payload(result):
    if not isinstance(result, dict) or "result" not in result:
        return result
    content = result["result"].get("structuredContent")
    if content is not None:
        return content
    texts = [item.get("text", "") for item in result["result"].get("content", []) if item.get("type") == "text"]
    joined = "\n".join(texts)
    try:
        return json.loads(joined)
    except ValueError:
        return {"text": joined, "isError": result["result"].get("isError")}


def size(value):
    return len(json.dumps(value, ensure_ascii=False))


def main(token_file):
    with open(token_file, encoding="utf-8") as handle:
        token = json.load(handle)["access_token"]
    client = McpClient(token)

    status, init = client.request("initialize", {
        "protocolVersion": "2025-06-18",
        "capabilities": {},
        "clientInfo": {"name": "uncode-builder-probe", "version": "0.1"},
    })
    server = init.get("result", {}).get("serverInfo") if isinstance(init, dict) else init
    print("== initialize:", status, server, "| session:", bool(client.session_id))
    client.request("notifications/initialized", notification=True)

    status, listing = client.request("tools/list")
    tools = listing.get("result", {}).get("tools", []) if isinstance(listing, dict) else []
    print("== tools/list:", status, len(tools), "herramientas visibles con este token:")
    for tool in tools:
        print("   -", tool["name"], "| readOnly:", tool.get("annotations", {}).get("readOnlyHint"))

    names = {tool["name"] for tool in tools}
    prefix = "wp_ability_uncode_builder_"

    def call(short_name, arguments):
        name = prefix + short_name
        if name not in names:
            return None, f"{name} no visible"
        return client.request("tools/call", {"name": name, "arguments": arguments})

    status, result = call("list_elements", {"search": "button"})
    payload = tool_payload(result)
    print("== list_elements(search=button):", status, [e.get("tag") for e in payload.get("elements", [])] if isinstance(payload, dict) else payload)

    status, result = call("get_element_schema", {"tag": "vc_column_text", "group": "General"})
    payload = tool_payload(result)
    if isinstance(payload, dict) and "params" in payload:
        print("== get_element_schema(vc_column_text, General):", status, len(payload["params"]), "params,", size(payload), "bytes")
    else:
        print("== get_element_schema:", status, payload)

    status, result = call("get_page_tree", {"post_id": DRAFT_PAGE_ID})
    payload = tool_payload(result)
    if isinstance(payload, dict) and "tree" in payload:
        gradient = payload["tree"][0].get("decoded", {}).get("back_color_gradient", {}).get("type")
        raw_html = payload["tree"][1]["children"][0]["children"][0]["children"][1]["children"][0].get("decoded_content")
        print("== get_page_tree(borrador):", status, payload["post"]["status"], "| elementos:", payload["element_count"], "| gradiente:", gradient, "| html crudo:", raw_html)
    else:
        print("== get_page_tree(borrador):", status, payload)

    status, result = call("get_element_schema", {"tag": "vc_row", "inventado": True})
    print("== argumento no permitido:", status, str(tool_payload(result))[:200])

    status, result = client.request("tools/call", {"name": "wp_list_pages", "arguments": {}})
    print("== herramienta fuera del scope (wp_list_pages):", status, str(tool_payload(result))[:200])

    anonymous = McpClient("token-invalido")
    status, result = anonymous.request("tools/list")
    print("== token inválido:", status, str(result)[:160])


if __name__ == "__main__":
    main(sys.argv[1])
