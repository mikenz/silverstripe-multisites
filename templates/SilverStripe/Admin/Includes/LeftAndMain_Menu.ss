<div class="fill-height cms-menu cms-panel cms-panel-layout" id="cms-menu" data-layout-type="border">
    <div class="cms-logo-header">
        <% include SilverStripe\\Admin\\LeftAndMain_MenuLogo %>
        <% include SilverStripe\\Admin\\LeftAndMain_MenuStatus %>

        <% if $ListSubsites %>
            <% include SubsiteList %>
        <% end_if %>
    </div>

    <div class="flexbox-area-grow panel--scrollable panel--triple-toolbar cms-panel-content">
        <% include SilverStripe\\Admin\\LeftAndMain_MenuList %>
    </div>

    <% if $ApplicationName == 'Prod' %>
    <div class="toolbar toolbar--south cms-panel-environment" style="color: red">
        <div>Production</div>
    </div>
    <% else_if $ApplicationName == 'Pp' %>
    <div class="toolbar toolbar--south cms-panel-environment" style="color: #9ea517">
        <div>Pre Prod</div>
    </div>
    <% else %>
    <div class="toolbar toolbar--south cms-panel-environment" style="color: #7d889b">
        <div>$ApplicationName.UpperCase</div>
    </div>
    <% end_if %>

    <div class="toolbar toolbar--south cms-panel-toggle">
        <% include SilverStripe\\Admin\\LeftAndMain_MenuToggle %>
    </div>
</div>