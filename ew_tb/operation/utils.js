function sjs(json_object) {
	return JSON.stringify(json_object);
}

// rows will be modified
function build_column_header(rows, pkey, fields) {
	var cols = [];
	var d = {}; // probe columns and stringify json list/dict
	for ( var j = 0; j < rows.length; j++) {
		for ( var k in rows[j]) {
			var found = false;
			for ( var p in d) {
				if (p == k)
					found = true;
			}
			if (!found && (!fields || (fields && $.inArray(k, fields) != -1))) {
				cols.push(d[k] = {
					id : k,
					name : k,
					field : k,
					sortable : true
				});
			}

			var type = Object.prototype.toString.call(rows[j][k]);
			if (type === '[object Array]' || type === '[object Object]')
				rows[j][k] = JSON.stringify(rows[j][k]);
			if (k === pkey)
				rows[j]['id'] = rows[j][k];
		}
	}
	return cols;
};

function find_column(cols, col_id) {
	for ( var i = 0 ; i < cols.length ; i++ ) {
		if ( cols[i]['id'] === col_id ) {
			return cols[i];
		}
	}
	return undefined;
}

function set_column_formatter(cols, col_id, formatter) {
	for ( var i = 0 ; i < cols.length ; i++ ) {
		if ( cols[i]['id'] === col_id ) {
			cols[i]['formatter'] = formatter;
			break;
		}
	}
}

function build_slickgrid(sel_grid, rows, cols, sel_pager, rows_per_page) {
	var e = {selector:sel_grid.selector, length:sel_grid.length};
	console.log('building grid on ' + sjs(e));
	if ( sel_pager )
		console.log('building pager on ' + sel_pager.selector);
	
	// draw slickgrid
	var dataView;
	var grid;
	var columns = cols;
	var options = {
		editable : false,
		enableAddRow : false,
		enableCellNavigation : true,
		enableColumnReorder : true,
		multiColumnSort : true,
		forceFitColumns : true,
		autoHeight: true
	};

	dataView = new Slick.Data.DataView({
		inlineFilters : true
	});
	grid = new Slick.Grid(sel_grid, dataView, columns, options);
	grid.registerPlugin(new Slick.AutoTooltips({
		enableForHeaderCells : true
	}));
	grid.data = rows;
	grid.cols = cols;
	grid.dataView = dataView;
	grid.onSort.subscribe(function(e, args) {
		var cols = args.sortCols;
		args.grid.data.sort(function(dataRow1, dataRow2) {
			for ( var i = 0, l = cols.length; i < l; i++) {
				var field = cols[i].sortCol.field;
				var sign = cols[i].sortAsc ? 1 : -1;
				var value1 = dataRow1[field], value2 = dataRow2[field];
				var result = (value1 == value2 ? 0 : (value1 > value2 ? 1 : -1)) * sign;
				if (result != 0) {
					return result;
				}
			}
			return 0;
		});
		args.grid.invalidate();
		args.grid.render();
	});
	if (sel_pager) {
		if (rows_per_page) {
			dataView.setPagingOptions({
				pageSize : rows_per_page,
			});
		}
		grid.pager = new Slick.Controls.Pager(dataView, grid, sel_pager);
	}

	// wire up model events to drive the grid
	dataView.onRowCountChanged.subscribe(function(e, args) {
		grid.updateRowCount();
		grid.render();
	});

	dataView.onRowsChanged.subscribe(function(e, args) {
		grid.invalidateRows(args.rows);
		grid.render();
	});
	dataView.beginUpdate();
	dataView.setItems(rows);
	dataView.endUpdate();

	return grid;
}