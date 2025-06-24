<!--
  Core Framework - View File

  @license    MIT (https://mit-license.org/)
  @author     Louis Ouellet <louis@laswitchtech.com>
-->
<div class="col-12" id="layout"></div>
<script>
    $(document).ready(function(){
        $.ajax({
            url: '/endpoint.php/inventory/details?id=<?= $this->Request->getParams('GET', 'id') ?>',
            type: 'GET',dataType: 'json',
            error: function(xhr, status, error) {
                let color = 'info', icon = 'question-circle', title = builder.Locale.get(xhr.statusText), content = builder.Locale.get(xhr.responseText);
                switch(xhr.status){
                    case 403: color = 'danger'; icon = 'shield-lock'; break;
                    case 404: color = 'warning'; icon = 'question-diamond'; break;
                    case 500: color = 'danger'; icon = 'bug'; break;
                }
                builder.Component("alert","#layout",{icon:icon,color:color,title:title},function(alert,component){component.content.html('<pre class="m-0 p-2">'+content+'</pre>');});
            },
            success: function(response) {
                console.log(response);

                var contacts = [];
                for(const [key, value] of Object.entries(response.record.contacts ?? {})){
                    var text = value.vcard.name;
                    if(value.vcard.title != null){
                        text += ' - ' + value.vcard.title;
                    }
                    contacts.push({id:value.vcard.id,text:text});
                }
                contacts.push({id:response.record.vcard.id,text:response.record.vcard.name});

                // Set a default value for the logo
                var Logo = builder.Helper.favicon(response.record.vcard.website ?? window.location.origin);
                if(response.record.vcard.avatar != null && typeof response.record.vcard.avatar.uuid !== 'undefined'){
                    Logo = window.location.origin + '/files/get?uuid=' + response.record.vcard.avatar.uuid;
                }

                // Layout
                let Layout = $(document.createElement('div')).addClass('row g-3').appendTo('#layout');
                Layout.details = $(document.createElement('div')).addClass('col-12').appendTo(Layout);
                Layout.steps = $(document.createElement('div')).addClass('col-12').appendTo(Layout);
                Layout.tabs = $(document.createElement('div')).addClass('col-8').appendTo(Layout);
                Layout.extra = $(document.createElement('div')).addClass('col-4').appendTo(Layout);

                // Details
                const Details = builder.Component(
                    "card",
                    Layout.details,
                    {
                        icon: "buildings",
                        title: response.record.vcard.name + " - <small>"+builder.Locale.get('Lead')+"</small>",
                    },
                    function(card,component){

                        // Set a minimum height to cover the controls
                        component.body.addClass('d-flex justify-content-start align-items-center').css('min-height','88px');

                        // Controls
                        component.body.controls = $(document.createElement('div')).addClass('position-absolute top-0 end-0 me-3 mt-5').appendTo(component.body);

                        // // Control - Subscriptions
                        // component.body.controls.subscription = $(document.createElement('button')).addClass('btn btn-sm btn-light me-2').attr('data-action','subscribe').attr('data-user',USER_USERNAME).text(builder.Locale.get('Subscribe')).appendTo(component.body.controls);
                        // component.body.controls.subscription.icon = $(document.createElement('i')).addClass('bi bi-bell me-1').prependTo(component.body.controls.subscription);

                        // // Control - Wave
                        // component.body.controls.wave = $(document.createElement("button")).attr({
                        //     'class': 'btn btn-sm btn-purple me-2',
                        //     'data-action': 'wave',
                        //     'data-relationship': '{"inventory": "' + response.record.id + '"}',
                        // }).text(builder.Locale.get('Wave')).appendTo(component.body.controls);
                        // component.body.controls.wave.icon = $(document.createElement("i")).addClass("bi bi-person-raised-hand me-1").prependTo(component.body.controls.wave);

                        // Controls - Group
                        component.body.controls.group = $(document.createElement('div')).addClass('btn-group').appendTo(component.body.controls);

                        // Control - Edit
                        component.body.controls.edit = $(document.createElement('button')).addClass('btn btn-sm btn-warning').text(builder.Locale.get('Edit')).appendTo(component.body.controls.group);
                        component.body.controls.edit.icon = $(document.createElement('i')).addClass('bi bi-pencil me-1').prependTo(component.body.controls.edit);
                        component.body.controls.edit.click(function(){
                            vCardModalEdit(response.record.vcard);
                        });

                        // Control - Archive
                        if(response.record.isArchived === 0){
                            component.body.controls.archive = $(document.createElement('button')).addClass('btn btn-sm btn-dark').text(builder.Locale.get('Archive')).appendTo(component.body.controls.group);
                            component.body.controls.archive.icon = $(document.createElement('i')).addClass('bi bi-archive me-1').prependTo(component.body.controls.archive);
                            component.body.controls.archive.click(function(){
                                LeadModalArchive(response.record);
                            });
                        }

                        // Layout of the body
                        component.body.logo = $(document.createElement('div')).addClass('flex-shrink-1 d-flex flex-column justify-content-center align-items-center p-2 px-3 me-3').appendTo(component.body);
                        component.body.row = $(document.createElement('div')).addClass('row g-3 w-100 py-3').appendTo(component.body);

                        // Add the lead's favicon to the card
                        component.body.logo.favicon = $(document.createElement('div')).addClass('rounded-circle border border-3 border-light d-flex justify-content-center align-items-center position-relative').css({"height": "256px", "width": "256px"}).appendTo(component.body.logo);
                        component.body.logo.favicon.img = $(document.createElement('img')).attr({
                            "class": "rounded-circle",
                            "src": Logo,
                            "data-type": "avatar",
                            "data-vcard": response.record.vcard.id,
                            "style": "max-height: 250px; max-width: 250px; height: 250px; width: 250px; object-fit: contain; object-position: center;",
                        }).appendTo(component.body.logo.favicon);
                        component.logo = $(document.createElement('button')).attr({
                            "type": "button",
                            "class": "ms-1 btn btn-sm btn-info fs-5 rounded-circle position-absolute",
                            "style": "transition: all 0.5s ease-in-out; height: 48px!important; width: 48px!important; bottom: 8px; right: 8px;",
                        }).html('<i class="bi bi-upload"></i>').appendTo(component.body.logo.favicon);
                        component.logo.click(function(){
                            vCardModalAvatar(response.record.vcard);
                        });

                        // Add the prospect's name to the card
                        component.vcard = $(document.createElement('div')).attr({
                            "class": "mt-2 position-relative text-center",
                        }).appendTo(component.body.logo);
                        component.fullname = $(document.createElement('h3')).attr({
                            "class": "m-0 fw-lighter d-block-inline",
                            "style": "max-width: 256px;",
                        }).text(response.record.vcard.name).appendTo(component.vcard);
                        if(response.record.vcard.dba){
                            component.dba = $(document.createElement('h4')).attr({
                                "class": "m-0 fw-lighter d-block-inline text-muted",
                                "style": "max-width: 256px;",
                            }).text(response.record.vcard.dba).appendTo(component.vcard);
                        }
                        component.vcard.btn = $(document.createElement('button')).attr({
                            "type": "button",
                            "class": "ms-1 btn btn-sm btn-primary fs-5 rounded-circle position-absolute",
                            "style": "transition: all 0.5s ease-in-out; height: 48px; width: 48px; top: calc(50% - 24px); right: -56px;",
                        }).html('<i class="bi bi-person-vcard"></i>').appendTo(component.vcard);
                        component.vcard.btn.click(function(){
                            vCardModal(response.record.vcard.id,response.record.vcard.name);
                        });

                        // Add the row to the search
                        builder.Search.add(component.body.row);

                        // Add the lead's information to the card
                        for(const [key, value] of Object.entries(response.record)){
                            switch(key){
                                case 'assignedTo':
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-4').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get('Assigned To')).appendTo(component.body.row[key]);
                                    component.body.row[key].object = $(document.createElement('div')).attr({
                                        "class": 'd-flex align-items-center pb-2',
                                        "data-type": 'avatar',
                                        "data-task": response.record.task.id,
                                    }).appendTo(component.body.row[key]);
                                    component.body.row[key].object.username = $(document.createElement('span')).attr({
                                        "class": "my-1",
                                        "data-bs-toggle": "tooltip",
                                        "data-bs-placement": "top",
                                        "title": response.record.assignedTo.username,
                                        "data-bs-title": response.record.assignedTo.username,
                                    }).text(response.record.assignedTo.username).appendTo(component.body.row[key].object);
                                    component.body.row[key].object.avatar = $(document.createElement('img')).attr({
                                        "class": "rounded-circle me-1",
                                        "alt": response.record.assignedTo.username,
                                        "style": "width: 48px; height: 48px;",
                                        "src": "/avatar?username="+response.record.assignedTo.username,
                                    }).prependTo(component.body.row[key].object);
                                    component.body.row[key].hover(
                                        function(){
                                            component.body.row[key].addClass('text-bg-secondary cursor-pointer rounded');
                                        },
                                        function(){
                                            component.body.row[key].removeClass('text-bg-secondary cursor-pointer rounded');
                                        },
                                    );
                                    component.body.row[key].click(function(){
                                        TaskAssignModal(response.record.task);
                                    });
                                    builder.Search.set(component.body.row[key]);
                                    break;
                            }
                        }

                        // Add the lead's information to the card
                        for(const [key, value] of Object.entries(response.record.task)){
                            switch(key){
                                case 'progress':
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-4').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get("Status")).appendTo(component.body.row[key]);
                                    component.body.row[key].object = $(document.createElement('h4')).addClass('w-100 m-0').appendTo(component.body.row[key]);
                                    component.body.row[key].badge = $(document.createElement('span')).addClass('badge w-100').attr({
                                        "data-type": "status",
                                        "data-task": response.record.task.id,
                                    }).appendTo(component.body.row[key].object);
                                    if(response.record.task.progress > 0){
                                        component.body.row[key].badge.addClass('text-bg-'+response.record.task.process[response.record.task.progress].color).text(builder.Locale.get(response.record.task.process[response.record.task.progress].name));
                                        component.body.row[key].badge.icon = $(document.createElement('i')).addClass('me-1 bi bi-'+response.record.task.process[response.record.task.progress].icon).prependTo(component.body.row[key].badge);
                                    } else {
                                        component.body.row[key].badge.addClass('text-bg-success').text(builder.Locale.get('New'));
                                        component.body.row[key].badge.icon = $(document.createElement('i')).addClass('me-1 bi bi-stars').prependTo(component.body.row[key].badge);
                                    }
                                    builder.Search.set(component.body.row[key]);
                                    break;
                                case 'priority':
                                    let color = ['secondary','primary','warning','orange','danger'];
                                    let name = ['Low','Normal','High','Urgent','Critical'];
                                    let icon = ['exclamation-triangle','info-circle','exclamation-circle','exclamation-diamond','exclamation-square'];
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-4').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get(key)).appendTo(component.body.row[key]);
                                    component.body.row[key].object = $(document.createElement('h4')).addClass('w-100 m-0').appendTo(component.body.row[key]);
                                    component.body.row[key].badge = $(document.createElement('span')).attr({
                                        "class": "badge w-100 text-bg-"+color[value],
                                        "data-type": "priority",
                                        "data-task": response.record.task.id,
                                    }).html('<i class="me-1 bi bi-'+icon[value]+'"></i>'+name[value]).appendTo(component.body.row[key].object);
                                    component.body.row[key].hover(
                                        function(){
                                            component.body.row[key].addClass('text-bg-secondary cursor-pointer rounded');
                                        },
                                        function(){
                                            component.body.row[key].removeClass('text-bg-secondary cursor-pointer rounded');
                                        },
                                    );
                                    component.body.row[key].click(function(){
                                        TaskPriorityModal(response.record.task);
                                    });
                                    builder.Search.set(component.body.row[key]);
                                    break;
                            }
                        }

                        // Add the lead's information to the card
                        for(const [key, value] of Object.entries(response.record.vcard)){
                            switch(key){
                                case 'address':
                                    const address = response.record.vcard.address + ', ' + response.record.vcard.city + ', ' + response.record.vcard.state + ' ' + response.record.vcard.zipcode + ', ' + response.record.vcard.country;
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-8').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get(key)).appendTo(component.body.row[key]);
                                    component.body.row[key].value = $(document.createElement('p')).addClass('text-nowrap m-0').text(address).appendTo(component.body.row[key]);
                                    builder.Search.set(component.body.row[key]);
                                    break;
                                case 'phone':
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-3').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get(key)).appendTo(component.body.row[key]);
                                    component.body.row[key].value = $(document.createElement('p')).addClass('text-nowrap m-0').text(value).appendTo(component.body.row[key]);
                                    builder.Search.set(component.body.row[key]);
                                    break;
                                case 'tags':
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-6').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get(key)).appendTo(component.body.row[key]);
                                    for(const [k, tag] of Object.entries(value)){
                                        // Create Button
                                        const object = $(document.createElement('span'))
                                            .addClass('badge text-bg-warning m-0 me-1 mb-1')
                                            .text(tag)
                                            .appendTo(component.body.row[key]);
                                        const icon = $(document.createElement('i')).addClass('bi bi-tag me-1').prependTo(object);
                                    }
                                    builder.Search.set(component.body.row[key]);
                                    break;
                                case 'industries':
                                    component.body.row[key] = $(document.createElement('div')).addClass('col-6').appendTo(component.body.row);
                                    component.body.row[key].header = $(document.createElement('p')).addClass('fw-bold text-capitalize text-nowrap').text(builder.Locale.get(key)).appendTo(component.body.row[key]);
                                    for(const [k, industry] of Object.entries(value)){
                                        // Create Button
                                        const object = $(document.createElement('span'))
                                            .addClass('badge text-bg-info m-0 me-1 mb-1')
                                            .text(industry)
                                            .appendTo(component.body.row[key]);
                                        const icon = $(document.createElement('i')).addClass('bi bi-crosshair me-1').prependTo(object);
                                    }
                                    builder.Search.set(component.body.row[key]);
                                    break;
                                default:
                                    break;
                            }
                        }
                    },
                )

                // Create a Tabs component
                const Tabs = builder.Component(
                    "tabs",
                    Layout.tabs,
                    {
                        class: {
                            navbar: 'nav-pills',
                        },
                    },
                    function(tabs,card){
                        card._component.body.removeClass('card-body');
                        tabs.add(
                            'notes',
                            {
                                icon: "stickies",
                                label: builder.Locale.get("Notes"),
                            },
                            function(tab,nav){
                                card.notes = tab;
                                NotesFeed(response.record.notes, tab, 'inventory', response.record.id);
                            },
                        );
                        tabs.add(
                            'contacts',
                            {
                                icon: "person-vcard",
                                label: builder.Locale.get("Contacts"),
                            },
                            function(tab,nav){
                                card.contacts = tab;
                                ContactsFeed(response.record.contacts, tab, {
                                    "category": "Contact",
                                    "address": response.record.vcard.address,
                                    "city": response.record.vcard.city,
                                    "country": response.record.vcard.country,
                                    "state": response.record.vcard.state,
                                    "zipcode": response.record.vcard.zipcode,
                                    "locale": response.record.vcard.locale,
                                    "phone": response.record.vcard.phone,
                                    "targetTable": "inventory",
                                    "targetId": response.record.id,
                                });
                            },
                        );
                        tabs.add(
                            'calls',
                            {
                                icon: "telephone",
                                label: builder.Locale.get("Calls"),
                            },
                            function(tab,nav){
                                card.calls = tab;
                                FollowupsTable("Call", response.record.followups, contacts, tab, {
                                    category: 'Call',
                                    targetTable: "inventory",
                                    targetId: response.record.id,
                                });
                            },
                        );
                        tabs.add(
                            'callbacks',
                            {
                                icon: "telephone-forward",
                                label: builder.Locale.get("Callbacks"),
                            },
                            function(tab,nav){
                                card.callbacks = tab;
                                FollowupsTable("Callback", response.record.followups, contacts, tab, {
                                    category: 'Callback',
                                    targetTable: "inventory",
                                    targetId: response.record.id,
                                });
                            },
                        );
                        tabs.add(
                            'appointments',
                            {
                                icon: "calendar2-event",
                                label: builder.Locale.get("Appointments"),
                            },
                            function(tab,nav){
                                card.appointments = tab;
                                FollowupsTable("Appointment", response.record.followups, contacts, tab, {
                                    category: 'Appointment',
                                    targetTable: "inventory",
                                    targetId: response.record.id,
                                });
                            },
                        );
                        tabs.add(
                            'files',
                            {
                                icon: "file-earmark",
                                label: builder.Locale.get("Files"),
                            },
                            function(tab,nav){
                                card.files = tab;
                                FilesFeed(response.record.files ?? [], tab, {
                                    targetTable: "inventory",
                                    targetId: response.record.id,
                                    isPublic: 1,
                                });
                            },
                        );
                        tabs.add(
                            'documents',
                            {
                                icon: "file-earmark-richtext",
                                label: builder.Locale.get("Documents"),
                            },
                            function(tab,nav){
                                card.files = tab;
                                docvals = {
                                    "locale": response.record.vcard.locale,
                                    "avatar": "/plugins/inventory/logo?id="+response.record.id,
                                    "name": response.record.vcard.name,
                                    "title": response.record.vcard.title,
                                    "role": response.record.vcard.role,
                                    "address": response.record.vcard.address,
                                    "city": response.record.vcard.city,
                                    "state": response.record.vcard.state,
                                    "zipcode": response.record.vcard.zipcode,
                                    "country": response.record.vcard.country,
                                    "phone": response.record.vcard.phone,
                                    "mobile": response.record.vcard.mobile,
                                    "tollfree": response.record.vcard.tollfree,
                                    "fax": response.record.vcard.fax,
                                    "website": response.record.vcard.website,
                                    "businessNumber": response.record.vcard.businessNumber,
                                    "taxExtension": response.record.vcard.taxExtension,
                                    "importerExtension": response.record.vcard.importerExtension,
                                };
                                if(docvals.avatar){
                                    if(typeof docvals.avatar.id !== 'undefined'){
                                        docvals.avatar = docvals.avatar.id;
                                    }
                                }
                                DocumentsFeed(response.record.documents ?? [], tab, {
                                    targetTable: "inventory",
                                    targetId: response.record.id,
                                    isPublic: 1,
                                    locale: response.record.vcard.locale,
                                    docvals: docvals,
                                }, response.record.vcard.locale);
                            },
                        );
                        tabs.add(
                            'activities',
                            {
                                icon: "activity",
                                label: builder.Locale.get("Activity"),
                            },
                            function(tab,nav){
                                tab.addClass('px-4 py-3');
                                card.activities = tab;
                                EventFeed(response.record.events, tab);
                            },
                        );
                        tabs.add(
                            'related',
                            {
                                icon: "diagram-2",
                                label: builder.Locale.get("Related"),
                            },
                            function(tab,nav){
                                tab.addClass('px-4 py-3');
                                card.related = tab;
                                RelationshipFeed(response.relationships ?? [], tab);
                            },
                        );
                    },
                );

                // Create a Card for the task list
                const Progress = builder.Component(
                    "card",
                    Layout.extra,
                    {
                        class: {
                            component: "mb-3",
                            body: "p-0",
                        },
                        icon: "check-square",
                        title: builder.Locale.get("Tasks"),
                    },
                    function(card,component){
                        Layout.steps.card = $(document.createElement('div')).addClass('card card-body').appendTo(Layout.steps);
                        ProcessTree(response.record.task, Layout.steps.card, component.body);
                    },
                );
            }
        });
    });
</script>
