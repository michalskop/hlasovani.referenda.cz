{extends file='page.tpl'}
{block name=additionalHead}
<script src="//d3js.org/d3.v3.js"></script>
<script src="js/d3.tip.js"></script>
{/block}
{block name=body}
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header" id="infobox_header">
{include "party-page-top.tpl"}
    </div> <!-- /modal-header -->
    <div class="modal-body">
      {include "party-page-table.tpl"}
    </div> <!-- /modal-body-->
  </div> <!-- /modal content -->
</div> <!-- /modal -->
{if $show_chart}
    {include "chart.tpl"}
{/if}
{/block}
