from osint_engine.sdk import Node, clear_registry, get_registry, transform


def test_transform_decorator_registers():
    clear_registry()

    @transform(
        name="t.one",
        display_name="One",
        input_types=["domain"],
        output_types=["ipv4"],
    )
    def run(node, api_keys):
        return [Node(type="ipv4", value="1.2.3.4")]

    reg = get_registry()
    assert "t.one" in reg
    spec = reg["t.one"]
    assert spec.display_name == "One"
    assert spec.input_types == ["domain"]
    assert spec.output_types == ["ipv4"]
    assert spec.timeout == 30
    assert spec.func is run


def test_node_to_dict_defaults_label():
    n = Node(type="domain", value="example.com")
    d = n.to_dict()
    assert d == {"type": "domain", "value": "example.com", "label": "example.com", "data": {}}


def test_transform_wildcard_default():
    clear_registry()

    @transform(name="t.any", display_name="Any")
    def run(node, api_keys):
        return []

    assert get_registry()["t.any"].input_types == ["*"]
