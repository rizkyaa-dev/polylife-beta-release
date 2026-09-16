{{--
    Progressive enhancement only: browsers may ignore this hint at any time.
    The underlying links remain ordinary same-origin GET navigations.
--}}
<script type="speculationrules" data-workspace-mode-acceleration>
{
    "prerender": [
        {
            "where": { "selector_matches": "a[data-instant-workspace-navigation]" },
            "eagerness": "moderate"
        }
    ]
}
</script>
