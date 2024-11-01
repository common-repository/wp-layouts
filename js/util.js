var AGSLayoutsUtil = {
	replaceFunctionWithFunction: function(sourceObject, functionFieldName, replacementFunction) {
		var checkInterval = setInterval(function() {
			if (sourceObject[functionFieldName]) {
				clearInterval(checkInterval);
				sourceObject['ags_layouts_orig__' + functionFieldName] = sourceObject[functionFieldName];
				sourceObject[functionFieldName] = function() {
					var newArgs = [];
					for (var i = 0; i < arguments.length; ++i) {
						newArgs.push(arguments[i]);
					}
					replacementFunction.apply(null, newArgs);
				};
			}
		}, 500);
	}
};