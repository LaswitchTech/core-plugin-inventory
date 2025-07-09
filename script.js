const InventoryFormat = function(element, commission, list){

    // Find the list component
    const listElement = element.parent();

    // Find the first note item in the list
    const first = listElement.find('[data-type="commission"]').first();

    // Insert the note before the first note item
    if(first.length){
        element.insertBefore(first);
    }

    // Save the note in the element
    element.commission = commission;

    // Set attributes
    element.attr({
        "data-type": "commission",
    });

    // Set Styling
    element.removeClass('cursor-pointer').css('transition', '0.5s ease-in-out');
    setTimeout(function(){
        if(typeof element.actions !== 'undefined'){
            element.actions.remove();
        }
    }, 100);

    // Add a container for the controls
    element.controls = $(document.createElement('div')).addClass('flex-shrink-1 pe-3 py-2').appendTo(element.container);
    element.controls.group = $(document.createElement('div')).addClass('btn-group').appendTo(element.controls);

    // Add a button to remove the commission
    element.controls.group.delete = $(document.createElement('button')).addClass('btn btn-sm btn-danger').html('<i class="bi bi-trash"></i>').appendTo(element.controls.group);
    element.controls.group.delete.click(function(){
        delete list._items[element.id];
        element.remove();
    });
};
const InventoryModal = function(id, callback = null){

    // AJAX Request
    $.ajax({
        url: '/api/inventory/cap',
        headers: {'X-CSRF-Authorization': CSRF_KEY},
        type: 'GET',dataType: 'json',
        success: function(commissionCap) {
            commissionCap = parseInt((commissionCap * 100));

            // AJAX Request
            $.ajax({
                url: '/api/inventory/fetch?id=' + id,
                headers: {'X-CSRF-Authorization': CSRF_KEY},
                type: 'GET',dataType: 'json',
                success: function(response) {

                    // Set the response in the storage
                    builder.Storage.set(response.record ?? [], null,'inventory:'+id);
                    var product = builder.Storage.get('product','inventory:'+id);

                    // Create a Modal
                    builder.Component(
                        "modal",
                        {
                            onEnter: false,
                            destroy:true,
                            icon: "box-seam",
                            title: builder.Locale.get("Product Details"),
                            cancel: false,
                            submit: true,
                            size: "lg",
                            callback: {
                                submit: function(element,modal){

                                    // Create a spinner animate-rotate
                                    element.spinner = $(document.createElement('div')).attr({
                                        "class": "animate-rotate rounded-circle border border-secondary border-4 d-none",
                                        "style": "width: 96px; height: 96px; border-top-color: var(--bs-primary)!important;",
                                    }).appendTo(element);

                                    // Hide the dialog
                                    element.dialog.addClass('opacity-0');

                                    // Setup a spinner while waiting for the modal to be submitted
                                    setTimeout(() => {

                                        // Hide the dialog
                                        element.dialog.hide();

                                        // Add flex to the modal
                                        element.addClass('d-flex align-items-center justify-content-center');

                                        // Show the spinner
                                        element.spinner.removeClass('d-none');

                                        // Submit the form
                                        element.form.submit();
                                    }, 300);

                                },
                            },
                        },
                        function(modal,component){
                            const componentModal = component;
                            component.header.addClass('text-bg-primary');
                            component.body.addClass('p-0');
                            component.footer.submit
                                .addClass('btn-success')
                                .removeClass('btn-link')
                                .text(builder.Locale.get('Save Changes'))
                                .attr('style','border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;');
                            component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-save me-1').prependTo(component.footer.submit);

                            // Create a form inside the modal
                            component.form = builder.Component(
                                "form",
                                component.body,
                                {
                                    class: {
                                        component: "row g-3 p-3",
                                    },
                                    callback:{
                                        val: function(values){
                                            for(const [key, value] of Object.entries(values)){
                                                switch(key){
                                                    case 'rate':
                                                    case 'price': values[key] = parseFloat((value / 100)); break;
                                                    default: values[key] = parseInt(value); break;
                                                }
                                            }
                                            return values;
                                        },
                                        submit: function(form){
                                            var currentValues = form.val();
                                            currentValues.commissions = [];
                                            currentValues[product.inColumn] = parseInt((currentValues[product.inColumn] * 100));
                                            var currentCap = Math.min(commissionCap, currentValues[product.inColumn]);
                                            var commissionCurrent = 0;
                                            for(const [key, item] of Object.entries(component.list.get())){
                                                commissionCurrent += parseInt((item.commission.rate * 100));
                                                currentValues.commissions.push({rate: item.commission.rate, user: item.commission.user.id});
                                            }

                                            // Check if the commission exceeds the cap
                                            if(commissionCurrent > currentCap){

                                                // Hide the spinner
                                                component.spinner.addClass('d-none');

                                                // Remove flex to the modal
                                                component.removeClass('d-flex align-items-center justify-content-center');

                                                // Show the dialog
                                                component.dialog.show();

                                                // Show the dialog
                                                component.dialog.removeClass('opacity-0');

                                                // Set the background to danger
                                                for(const [key, item] of Object.entries(component.list.get())){
                                                    item.addClass('text-bg-danger');
                                                }
                                            } else {

                                                // Divide the rate by 100
                                                currentValues[product.inColumn] = (currentValues[product.inColumn] / 100);

                                                // AJAX Request
                                                $.ajax({
                                                    url: '/api/inventory/update?id=' + id,
                                                    headers: {'X-CSRF-Authorization': CSRF_KEY},
                                                    type: 'POST',dataType: 'json',
                                                    data: currentValues,
                                                    success: function(response) {

                                                        // Set the response in the storage
                                                        builder.Storage.set(response.record, null,'inventory:'+id);

                                                        // Execute the callback
                                                        if (typeof callback === 'function') {
                                                            callback(response.record);
                                                        }

                                                        // Close the modal
                                                        modal.hide();
                                                    },
                                                });
                                            }
                                        },
                                    },
                                },
                                function(form,component){

                                    // Generate a list of products
                                    var options = [{id: product.id, text: product.type + ' | ' + product.name + '(' + product.sku + ')' + ' - ' + product.description}];

                                    // Add a select input for the product
                                    form.add(
                                        {
                                            class: {
                                                field: 'col-12',
                                            },
                                            name: 'product',
                                            label: builder.Locale.get('Product'),
                                            icon: 'box-seam',
                                            type: 'select2',
                                            modal: componentModal,
                                            options: options,
                                        }
                                    );

                                    // Add a text input for the qty
                                    form.add(
                                        {
                                            class: {
                                                field: 'col-12 col-md-6',
                                            },
                                            name: 'qty',
                                            label: builder.Locale.get('QTY'),
                                            icon: 'hash',
                                            type: 'number',
                                            value: builder.Storage.get('qty','inventory:'+id) || 1,
                                        },
                                    );

                                    // Add a float input for the price/rate
                                    form.add(
                                        {
                                            class: {
                                                field: 'col-12 col-md-6',
                                            },
                                            name: product.inColumn,
                                            label: builder.Locale.get('Price/Rate'),
                                            icon: 'currency-dollar',
                                            type: 'number',
                                            value: (builder.Storage.get(product.inColumn,'inventory:'+id) * 100) || 0,
                                        },
                                        function(input){
                                            input.input.attr('step', '1');
                                            input.input.attr('min', '0');
                                        }
                                    );

                                    // Show the modal
                                    modal.show();
                                },
                            );

                            // Create a list inside the modal
                            component.list = builder.Component(
                                "list",
                                component.body,
                                {
                                    class: {
                                        component: "bg-transparent",
                                    },
                                    tools: {
                                        add: {
                                            icon: "plus-lg",
                                            label: null,
                                            color: "success",
                                            class: null,
                                            callback: function(tool,list){
                                                var currentValues = component.form.val();
                                                currentValues[product.inColumn] = parseInt((currentValues[product.inColumn] * 100));
                                                var currentCap = Math.min(commissionCap, currentValues[product.inColumn]);
                                                var commissionCurrent = 0;
                                                for(const [key, item] of Object.entries(list.get())){
                                                    commissionCurrent += parseInt((item.commission.rate * 100));
                                                    console.log(item.commission.rate);
                                                }
                                                var commissionMax = (currentCap - commissionCurrent);
                                                console.log(currentCap, commissionCurrent, commissionMax);

                                                // Create a Modal
                                                builder.Component(
                                                    "modal",
                                                    {
                                                        onEnter: false,
                                                        destroy:true,
                                                        icon: "currency-dollar",
                                                        title: builder.Locale.get("Set a Commission"),
                                                        cancel: false,
                                                        submit: true,
                                                        size: "md",
                                                        callback: {
                                                            submit: function(element,modal){

                                                                // Create a spinner animate-rotate
                                                                var spinner = $(document.createElement('div')).attr({
                                                                    "class": "animate-rotate rounded-circle border border-secondary border-4 d-none",
                                                                    "style": "width: 96px; height: 96px; border-top-color: var(--bs-primary)!important;",
                                                                }).appendTo(element);

                                                                // Hide the dialog
                                                                element.dialog.addClass('opacity-0');

                                                                // Setup a spinner while waiting for the modal to be submitted
                                                                setTimeout(() => {

                                                                    // Hide the dialog
                                                                    element.dialog.hide();

                                                                    // Add flex to the modal
                                                                    element.addClass('d-flex align-items-center justify-content-center');

                                                                    // Show the spinner
                                                                    spinner.removeClass('d-none');

                                                                    // Submit the form
                                                                    element.form.submit();
                                                                }, 300);

                                                            },
                                                        },
                                                    },
                                                    function(modal,component){
                                                        const componentModal = component;
                                                        component.header.addClass('text-bg-primary');
                                                        component.body.addClass('bg-dark');
                                                        component.footer.submit
                                                            .addClass('btn-success')
                                                            .removeClass('btn-link')
                                                            .text(builder.Locale.get('Add'))
                                                            .attr('style','border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;');
                                                        component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-plus-lg me-1').prependTo(component.footer.submit);

                                                        // AJAX Request
                                                        $.ajax({
                                                            url: '/api/auth/colleagues',
                                                            type: 'GET',dataType: 'json',
                                                            success: function(members) {
                                                                var options = [];
                                                                for(const [id, member] of Object.entries(members)){
                                                                    options.push({id: id, text: member.username});
                                                                }

                                                                // Create a form inside the modal
                                                                component.form = builder.Component(
                                                                    "form",
                                                                    component.body,
                                                                    {
                                                                        callback:{
                                                                            val: function(values){
                                                                                for(const [key, value] of Object.entries(values)){
                                                                                    switch(key){
                                                                                        case 'rate': values[key] = (parseFloat(value) / 100); break;
                                                                                        case 'user': values[key] = members[parseInt(value)]; break;
                                                                                        default: values[key] = parseInt(value); break;
                                                                                    }
                                                                                }
                                                                                return values;
                                                                            },
                                                                            submit: function(form){

                                                                                // Set the commission
                                                                                const commission = form.val();

                                                                                // Add the commission to the list
                                                                                list.add(
                                                                                    {
                                                                                        field: (commission.rate * 100) + '% ' + builder.Locale.get('to') + ' ' + commission.user.username,
                                                                                    },
                                                                                    function(item,list){
                                                                                        InventoryFormat(item, commission, list);
                                                                                    },
                                                                                );

                                                                                // Close the modal
                                                                                modal.hide();
                                                                            },
                                                                        },
                                                                    },
                                                                    function(form,component){

                                                                        // Add a select input for the product
                                                                        form.add(
                                                                            {
                                                                                name: 'user',
                                                                                label: builder.Locale.get('User'),
                                                                                icon: 'person',
                                                                                type: 'select2',
                                                                                modal: componentModal,
                                                                                options: options,
                                                                            }
                                                                        );

                                                                        // Add a float input for the price/rate
                                                                        form.add(
                                                                            {
                                                                                name: 'rate',
                                                                                label: builder.Locale.get('Rate'),
                                                                                icon: 'currency-dollar',
                                                                                type: 'number',
                                                                            },
                                                                            function(input){
                                                                                input.addClass('mt-3');
                                                                                input.input.attr('step', 1);
                                                                                input.input.attr('min', 0);
                                                                                input.input.attr('max', commissionMax);
                                                                            }
                                                                        );

                                                                        // Show the modal
                                                                        modal.show();
                                                                    },
                                                                );
                                                            }
                                                        });
                                                    }
                                                );
                                            },
                                        },
                                    },
                                    icon: 'person',
                                    callback: {
                                        tool: null,
                                        action: null,
                                        item: null,
                                        click: null,
                                        dblclick: null,
                                    },
                                },
                                function(list,component){
                                    for(const [key, commission] of Object.entries(builder.Storage.get('commissions','inventory:'+id))){
                                        list.add(
                                            {
                                                field: (commission.rate * 100) + '% ' + builder.Locale.get('to') + ' ' + commission.user.username,
                                            },
                                            function(item,list){
                                                InventoryFormat(item, commission, list);
                                            },
                                        );
                                    }
                                },
                            );
                        }
                    );
                }
            });
        }
    });
}
const InventoryModalArchive = function(id, callback = null){

    // AJAX Request
    $.ajax({
        url: '/api/inventory/fetch?id=' + id,
        headers: {'X-CSRF-Authorization': CSRF_KEY},
        type: 'GET',dataType: 'json',
        success: function(response) {

            // Set the response in the storage
            builder.Storage.set(response.record ?? [], null,'inventory:'+id);
            var item = builder.Storage.get(null,'inventory:'+id);
            var product = builder.Storage.get('product','inventory:'+id);

            // Create a modal
            builder.Component(
                "modal",
                null,
                {
                    onEnter: false,
                    destroy: true,
                    icon: "archive",
                    title: builder.Locale.get("Are you sure you?"),
                    body: builder.Locale.get("Your are about to archive this item. Are you sure you want to continue?"),
                    cancel: false,
                    submit: true,
                    callback: {
                        submit: function(element,modal){

                            // Create a spinner animate-rotate
                            var spinner = $(document.createElement('div')).attr({
                                "class": "animate-rotate rounded-circle border border-secondary border-4 d-none",
                                "style": "width: 96px; height: 96px; border-top-color: var(--bs-primary)!important;",
                            }).appendTo(element);

                            // Hide the dialog
                            element.dialog.addClass('opacity-0');

                            // Setup a spinner while waiting for the modal to be submitted
                            setTimeout(() => {

                                // Hide the dialog
                                element.dialog.hide();

                                // Add flex to the modal
                                element.addClass('d-flex align-items-center justify-content-center');

                                // Show the spinner
                                spinner.removeClass('d-none');

                                // AJAX Request
                                $.ajax({
                                    url: '/api/inventory/archive?id='+id,
                                    type: 'GET',dataType: 'json',
                                    success: function(response) {

                                        // Set the response in the storage
                                        builder.Storage.remove('inventory:'+id);

                                        // Execute the callback
                                        if (typeof callback === 'function') {
                                            callback(response.record);
                                        }

                                        // Close the modal
                                        modal.hide();
                                    }
                                });
                            }, 300);
                        },
                    },
                },
                function(modal,component){

                    // Save the component
                    const componentModal = component;

                    // Style the modal
                    component.header.addClass('text-bg-dark');
                    component.footer.submit.addClass('btn-dark').removeClass('btn-link').attr({
                        "style": "border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;",
                    }).text(builder.Locale.get('Archive'));
                    component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-archive me-1').prependTo(component.footer.submit);

                    // Open the modal
                    modal.show();
                },
            );
        },
    });
};
const InventoryFeed = function(key, container, callback = null){

    var table = key.split(':')[0];
    var items = builder.Storage.get('dependencies:inventory',key);

    // Set Actions
    var actions = {
        details:{
            label:'Details',
            icon:'eye',
            action:function(event, table, dt, node, row, data){
                InventoryModal(data.id, function(product){
                    builder.Storage.set(product, 'dependencies:inventory:'+data.id,key);
                });
            }
        },
        archive:{
            label:'Archive',
            icon:'archive',
            action:function(event, table, dt, node, row, data){
                InventoryModalArchive(data.id, function(item){
                    table.delete(row);
                });
            }
        },
    };

    // Set Buttons
    var buttons = [
        {
            className : 'btn-success',
            init: function (dt, node){
                $(node).removeClass('btn-secondary');
            },
            text: '<i class="bi bi-plus-lg"></i>',
            action:function(e, dt, node, config){
                process_function_InventoryAddProduct({target:builder.Storage.get('record',key),targetTable:table,targetId:builder.Storage.get('record:id',key)}, null);
            },
        }
    ];

    // Column Definitions
    var columnDefs = [
        { target: 0, visible: false, title: builder.Locale.get('ID'), name: 'id', data: 'id', render: function(data, type, row) {
            var object = $(document.createElement('span'))
                .addClass('my-2')
                .text(data)
            return object.prop('outerHTML');
        }},
        { target: 1, visible: false, title: builder.Locale.get('Type'), name: 'type', data: 'type', render: function(data, type, row) {
            var object = $(document.createElement('span'))
                .addClass('my-2')
                .text(row.product.type)
            return object.prop('outerHTML');
        }},
        { target: 2, visible: true, title: builder.Locale.get('Name'), name: 'name', data: 'name', render: function(data, type, row) {
            var object = $(document.createElement('span'))
                .addClass('my-2')
                .text(row.product.name)
            return object.prop('outerHTML');
        }},
        { target: 3, visible: true, title: builder.Locale.get('Status'), name: 'isActive', data: 'isActive', render: function(data, type, row) {
            var object = $(document.createElement('span'))
                .addClass('badge')
            if(row.isActive){
                object.addClass('text-bg-success').text(builder.Locale.get('Active'));
            } else {
                object.addClass('text-bg-danger').text(builder.Locale.get('Inactive'));
            }
            return object.prop('outerHTML');
        }},
        { target: 4, visible: false, title: builder.Locale.get('QTY'), name: 'qty', data: 'qty', render: function(data, type, row) {
            var object = $(document.createElement('span'))
                .addClass('my-2')
                .text(row.qty)
            return object.prop('outerHTML');
        }},
        { target: 5, visible: true, title: builder.Locale.get('Price/Rate'), name: 'price', data: 'price', render: function(data, type, row) {
            var object = $(document.createElement('span'))
                .addClass('my-2')
                .text((row.rate * 100).toFixed(2) + '%')
            if(row.product.inColumn === 'price'){
                object.text(row.product.price.toFixed(2) + ' ' + row.currency);
            }
            return object.prop('outerHTML');
        }},
        { target: 6, visible: true, title: builder.Locale.get('Commissions'), name: 'commissions', data: 'commissions', render: function(data, type, row) {
            // If no commissions
            if(row.commissions == null || row.commissions == ''){
                return '';
            }

            // Get the commissions
            const commissions = row.commissions;

            // Create element
            var element = $(document.createElement('div')).addClass('d-flex flex-wrap flex-row');

            // Loop through the commissions
            for(const [key, commission] of Object.entries(commissions)){

                // Create Badge
                var object = $(document.createElement('span'))
                    .addClass('badge text-bg-blue m-1')
                    .attr('data-bs-toggle','tooltip')
                    .attr('data-bs-placement','top')
                    .attr('title',(commission.rate * 100).toFixed(2) + '%' + ' ' + builder.Locale.get('to') + ' ' + commission.user.username)
                    .attr('data-bs-title',(commission.rate * 100).toFixed(2) + '%' + ' ' + builder.Locale.get('to') + ' ' + commission.user.username)
                    .text((commission.rate * 100).toFixed(2) + '%')
                    .css('font-size','0.8rem');

                // Create icon
                var icon = $(document.createElement('i'))
                    .addClass('me-1 bi bi-wallet')
                    .prependTo(object);

                // Append to element
                object.appendTo(element);
            }

            // Return element
            return element.prop('outerHTML');
        }},
    ];

    // Create the table
    builder.Component(
        "table",
        container,
        {
            class: {
                buttons: "px-4 pt-4",
                table: "border-top",
                footer: "px-4 pt-2 pb-4",
            },
            showButtonsLabel: false,
            selectTools:false,
            actions:actions,
            datatable:{
                columnDefs:columnDefs,
                buttons:buttons,
                order: [[5, 'asc']],
            },
            dblclick:function(event, table, dt, node, data){
                actions.details.action(event, table, dt, node, null, data);
            },
        },
        function(table,component){
            component.attr({
                "data-type": "inventory",
            })
            for(const [key, record] of Object.entries(items)){
                table.add(record);
            }
            if(typeof callback === 'function'){
                callback(table, component);
            }
        },
    );
}

// Add a product to the inventory
function process_function_InventoryAddProduct(task, value, callback = null){

    var targetTable = task.targetTable;
    var targetId = task.targetId;

    // Check if the task has a target
    if(typeof task.target !== 'undefined'){
        if(typeof task.target.targetTable !== 'undefined'){
            targetTable = task.target.targetTable;
        }
        if(typeof task.target.targetId !== 'undefined'){
            targetId = task.target.targetId;
        }
    }

    // Check if the task is attached to a client
    if(task.targetTable === 'clients'){
        targetTable = task.targetTable;
        targetId = task.targetId;
    }

    // Check if the task is attached to a lead
    if(task.targetTable === 'leads'){
        targetTable = 'clients';
        targetId = task.target.client.id;
    }

    var key = builder.Storage.getKey();
    var table = key.split(':')[0];

    ProductsLookup(value, function(products){
        ProductsSelect(products, function(selection){

            // Select the product from the products list
            var product = products[selection.id];

            // Create the item to add to the inventory
            var item = {
                product: product.id,
                qty: selection.qty || 1,
                price: 0,
                rate: 0,
                currency: 'CAD',
                commissions: '[]',
                targetTable: targetTable,
                targetId: targetId,
            }

            // Set the price and rate based on the product
            item[product.inColumn] = selection.rate;

            // AJAX Request
            $.ajax({
                url: '/api/inventory/create',
                headers: {'X-CSRF-Authorization': CSRF_KEY},
                type: 'POST',dataType: 'json',
                data: item,
                success: function(response) {

                    // Check if the targetTable of the task is set to table
                    if(task.targetTable === table){

                        // If the targetTable is the same as the table, add the item to the table
                        builder.Storage.set(response.record, 'dependencies:inventory:'+response.record.id, key);
                    }

                    // If a callback is provided, call it with the response
                    if (typeof callback === 'function') {
                        callback(task, key, response.record);
                    }
                }
            });
        });
    });
};
function process_meta_InventoryAddProduct(key = null){
    const metadata = {
        label: "Add a Product",
        description: "Add a Product to the Inventory",
        type: "none",
    };
    return metadata[key] ? metadata[key] : metadata;
}
