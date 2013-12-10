var editor;

var Cheryl =
	angular.module('Cheryl', ['ngRoute'])
	.config(function($routeProvider){
		$routeProvider
			.when('/logout', {
				action: 'logout',
				controller: 'LogoutCtrl',
			})
			.when('/login', {
				action: 'login',
				controller: 'LoginCtrl',
			})
			.when('/NewFile', {
				action: 'newfile',
				controller: 'RootCtrl',
			})
			.otherwise({
				action: 'home',
				controller: 'RootCtrl'
			});
	})	
	.config(function($locationProvider){
		$locationProvider.html5Mode(true).hashPrefix('!');
	})
	.controller('RootCtrl', function ($scope, $http, $location, $anchorScroll, $route) {
		$scope.now = new Date;
		$scope.yesterday = new Date;
		$scope.yesterday.setDate($scope.yesterday.getDate() - 1);
		
		$scope.user = [];
		$scope.welcomeDefault = $scope.welcome = 'Welcome!';
		
		$scope.types = [];
		$scope.dates = [];
		$scope.type = 'dir';
		$scope.dialog = false;
		$scope.config = false;
		
		$scope.dateFormat = 'm/d/Y H:i:s';
		
		$scope.path = function() {
			return $scope.script;
		};

		$scope.dirPath = function() {
			return $location.path().replace($scope.script,'') || '/';
		};
		
		var password = function() {
			return encodeURIComponent(new jsSHA($scope.user.password + '<?php echo CHERYL_SALT; ?>', 'TEXT').getHash('SHA-1', 'HEX'));
		};
		
		$scope.getConfig = function() {
			$http({method: 'GET', url: $scope.path() + '?__p=config'}).
				success(function(data) {
					if (data.status) {
						$scope.authed = data.authed;
						$scope.config = true;
					}
				});
		};
		
		$scope.login = function() {
			$http.post($scope.path() + '/', {'__p': 'login', '__username': $scope.user.username, '__hash': password()}).
				success(function(data) {
					if (data.status) {
						$scope.welcome = $scope.welcomeDefault;
						$scope.authed = true;
						$scope.user.password = '';
					} else {
						$scope.welcome = 'Try again!';
					}
				}).
				error(function(data) {
					$scope.welcome = 'Try again!';
				});	
		};
		
		$scope.authed = false;
		$scope.script = '<?php echo Cheryl::script() ?>';
		
		$scope.dateFilterNames = {
			0: 'Today',
			1: 'Last week',
			2: 'Last month',
			3: 'Archive'
		};

		$scope.filters = {
			recursive: 0,
			types: [],
			dates: [],
			search: ''
		};
		
		$scope.uploads = [];
		
		$scope.filter = function(filter, value) {
			switch (filter) {
				case 'recursive':
					$scope.filters[filter] = value;
					break;
				case 'types':
				case 'dates':
					$scope.filters[filter][value] = !$scope.filters[filter][value];
					var hasValue = false;
					for (var x in $scope.filters[filter]) {
						if ($scope.filters[filter][x]) {
							hasValue = true;
							break;
						}
					}
					if (!hasValue) {
						$scope.filters[filter] = [];
					}
					
					break;
				}
		};
		
		$scope.filterFiles = function(file) {
			if (Object.size($scope.filters.types)) {
				if (!$scope.filters.types[file.ext.toUpperCase()]) {
					return false;
				}
			}
			if (Object.size($scope.filters.dates)) {
				if (!$scope.filters.dates[file.dateFilter]) {
					return false;
				}
			}
			if ($scope.filters.search && file.name.indexOf($scope.filters.search) === -1) {
				return false;
			}
			return true;
		};
		
		$scope.filterCheck = function(filter, value) {
			return $scope.filters[filter][value];
		};
		
		var formatDate = function(file) {
			var time = new Date(file.mtime * 1000);
			var timeOfDay = (time.getHours() > 12 ? time.getHours() - 12 : time.getHours()) + ':' + time.getMinutes() + (time.getHours() > 12 ? ' PM' : ' AM');
			var daysAgo = Math.ceil(($scope.now.getTime() / 1000 / 60 / 60 / 24) - (time.getTime() / 1000 / 60 / 60 / 24));

			if ('' + time.getFullYear() + time.getMonth() + time.getDate() == '' + $scope.now.getFullYear() + $scope.now.getMonth() + $scope.now.getDate()) {
				file.dateReadable = 'Today @ ' + timeOfDay;
				file.dateFilter = 0;
				
			} else if ('' + time.getFullYear() + time.getMonth() + time.getDate() == '' + $scope.yesterday.getFullYear() + $scope.yesterday.getMonth() + $scope.yesterday.getDate()) {
				file.dateReadable = 'Yesterday @ ' + timeOfDay;
				file.dateFilter = 1;

			} else if (daysAgo < 7) {
				file.dateReadable = daysAgo + ' day' + (daysAgo == 1 ? '' : 's') + ' ago';
				file.dateFilter = 1;

			} else if (daysAgo < 28) {
				var weeks = Math.floor(daysAgo / 7);
				file.dateReadable = weeks + ' week' + (weeks == 1 ? '' : 's') + ' ago';
				file.dateFilter = 2;

			} else if (daysAgo < 363) {
				var months = Math.floor(daysAgo / 30);
				file.dateReadable = months + ' month' + (months == 1 ? '' : 's') + ' ago';
				file.dateFilter = 3;

			} else {
				var years = Math.floor(daysAgo / 365);
				file.dateReadable = years + ' year' + (years == 1 ? '' : 's') + ' ago';
				file.dateFilter = 3;
			}
			
			$scope.dates[file.dateFilter] = $scope.dates[file.dateFilter] ? $scope.dates[file.dateFilter] + 1 : 1;
		};
		
		$scope.formatSize = function(file) {
			var size = file.size;
			var i = -1;
			var byteUnits = [' KB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
			do {
				size = size / 1024;
				i++;
			} while (size > 1024);
			file.sizeReadable = Math.max(size, 0.1).toFixed(1) + byteUnits[i];
		};
		
		$scope.loadFiles = function() {
			if (!$scope.config) {
				$scope.getConfig();
				return;
			}
			if (!$scope.authed) {
				return;
			}
			$scope.fullscreenEdit = false;
			$scope.filters.search = '';
			
			if ($route.current.action == 'newfile') {
				console.log('NEW');
			}

			var url = $scope.path() + '?__p=ls&_d=' + $scope.dirPath();
			for (var x in $scope.filters) {
				url += '&filters[' + x + ']=' + $scope.filters[x];
			}
			
			$http({method: 'GET', url: url}).
				success(function(data) {
					$scope.type = data.type;
					$scope.file = data.file;
					$scope.file.nameUser = $scope.file.name;

					if (data.type == 'dir') {
						$scope.files = data.list.files;
						$scope.dirs = data.list.dirs;
						
						$scope.types = {};
						$scope.dates = {};

						for (var x in $scope.files) {
							var type = $scope.files[x].ext.toUpperCase();
							$scope.types[type] = $scope.types[type] ? $scope.types[type]+1 : 1;
							formatDate($scope.files[x]);
							$scope.formatSize($scope.files[x]);
						}
					} else {
						formatDate($scope.file);
						$scope.formatSize($scope.file);

					}
				}).
				error(function() {
					$scope.files = null;
				});	
		};

		$scope.$watch('filters.recursive', $scope.loadFiles);
		$scope.$watch('authed', $scope.loadFiles);
		$scope.$on('$locationChangeSuccess', $scope.loadFiles);
		$scope.$on('$locationChangeSuccess', function() {
			$anchorScroll();
			$scope.loadFiles();
		});
		
		$scope.modes = {
			php: 'php',
			js: 'javascript',
			css: 'css',
			md: 'markdown',
			svg: 'svg',
			xml: 'xml',
			asp: 'asp',
			c: 'c',
			sql: 'mysql'
		};
		
		$scope.$watch('file.contents', function() {
			if (!$scope.file || !$scope.file.contents) {
				return;
			}

			if (!$scope.editor) {
				$scope.editor = ace.edit('editor');
				$scope.editor.renderer.setShowPrintMargin(false);
				$scope.editor.session.setWrapLimitRange(null, null);

				$scope.editor.commands.addCommand({
					name: 'saveFile',
					bindKey: {
						win: 'Ctrl-S',
						mac: 'Command-S',
						sender: 'editor|cli'
					},
					exec: function(env, args, request) {
						$scope.saveFile();
					}
				});
			}
			
			var mode = 'text';
			for (var x in $scope.modes) {
				if (x == $scope.file.ext) {
					mode = $scope.modes[x];
					break;
				}
			}

			$scope.editor.getSession().setMode('ace/mode/' + mode);

			$scope.editor.getSession().setValue($scope.file.contents);
			$scope.editor.setReadOnly(!$scope.file.writeable);

			fullscreenToggle();
		});
		
		var fullscreenToggle = function() {
			if (!$scope.editor) {
				return;
			}
			if ($scope.fullscreenEdit) {
				$scope.editor.renderer.setShowGutter(true);
				$scope.editor.setHighlightActiveLine(true);
				$scope.editor.session.setUseWrapMode(false);
				$scope.editor.setTheme('ace/theme/ambiance');
			} else {
				$scope.editor.renderer.setShowGutter(false);
				$scope.editor.setHighlightActiveLine(false);
				$scope.editor.session.setUseWrapMode(true);
				$scope.editor.setTheme('ace/theme/clouds');
			}
			setTimeout(function(){
				$scope.editor.resize();
			});
		};

		$scope.$watch('fullscreenEdit', fullscreenToggle);

		$scope.$watch('file.nameUser', function() {
			if (!$scope.file || $scope.file.name == $scope.file.nameUser) {
				return;
			}
			console.log('CHANGED');
		});

		$scope.downloadFile = function(event, file) {
			event.preventDefault();
			event.stopPropagation();
			var iframe = document.getElementById('downloader');
			iframe.src = $scope.path() + '?__p=dl&_d=' + file.path + '/' + file.name;
		};
		
		$scope.saveFile = function() {
			$scope.$apply(function() {
				var error = function() {
					$scope.dialog = {type: 'error', message: 'There was an error saving the file.'};
				};
				$http.post($scope.path() + '/', {
					'__p': 'sv',
					'_d': $scope.dirPath(),
					'c': $scope.editor.getSession().getValue()
				}).
				success(function(data) {
					if (data.status) {
						$scope.dialog = false;
					} else {
						error();
					}
				}).
				error(error);
			});
		};
		
		$scope.upload = function(event, scope) {
			var files = event.target.files || event.dataTransfer.files;

			for (var i = 0, f; f = files[i]; i++) {
				var xhr = new XMLHttpRequest();
				var file = files[i];

				if (xhr.upload && file.size <= 9000000000) {
					var fd = new FormData();
					fd.append(file.name, file);
					
					scope.$apply(function() {
					
						var upload = {
							name: file.name,
							path: $location.path(),
							size: file.size,
							uploaded: 0,
							progress: 0,
							status: 'uploading'
						};
						scope.formatSize(upload);
						scope.uploads.push(upload);


						xhr.upload.addEventListener('progress', function(e) {
							scope.$apply(function() {
								upload.uploaded = e.loaded;
								upload.progress = parseInt(e.loaded / e.total * 100);
							});
						}, false);

						xhr.onload = function() {
							var status = this.status;
							scope.$apply(function() {
								upload.status = (status == 200 ? 'success' : 'error');
								scope.loadFiles();
							});
							setTimeout(function() {
								scope.$apply(function() {
									scope.uploads.splice(scope.uploads.indexOf(upload), 1);
								});
							},5000);
						};

						xhr.open('POST', scope.path() + '/?__p=ul&_d=' + scope.dirPath(), true);
						xhr.setRequestHeader('X-File-Name', file.name);
						xhr.send(fd);
					});
				}
			}
		};
	})
	.directive('ngEnter', function() {
		return function(scope, element, attrs) {
			if (attrs.ngEnter) {
				element.bind('keydown keypress', function(event) {
					if (event.which === 13) {
						event.preventDefault();
						scope.$eval(attrs.ngEnter);
					}
				});
			}
		};
	})
	.directive('ngDropUpload', function () {
		return function (scope, element) {
	
			var dragEnd = function() {
				scope.$apply(function() {
					scope.dialog = false;
				});
			};
			
			var autoEndClean;

			angular.element(document).bind('dragover', function(event) {
				clearTimeout(autoEndClean);
				for (var x in event.dataTransfer.files) {
					console.log(event.dataTransfer.files[x]);
				}
				event.preventDefault();
				scope.$apply(function() {
					scope.dialog = {
						type: 'dropupload'
					}
				});
				autoEndClean = setTimeout(dragEnd,1000);
			});

			element
				.bind('drop', function(event) {
					event.preventDefault();
					scope.upload(event, scope);
				});
		}
	})
	.directive('ngUploader', function($location) {
		return function(scope, element) {
			element.bind('change', function(event) {
				scope.upload(event, scope);
			});
		};
	})
	.directive('ngUpload', function() {
		return function(scope, element) {
			element.bind('click', function(event) {
				document.querySelector('.upload').click();
			});
		};
	})
	.directive('ngMakeDir', function($http, $location) {
		return function(scope, element) {
			element.bind('click', function(event) {
				scope.$apply(function() {
					scope.dialog = {
						type: 'makeDir',
						path: scope.file,
						file: '',
						no: function() {
							scope.dialog = false;
						},
						yes: function() {
							if (!scope.dialog.file) {
								return;
							}
							var error = function() {
								scope.dialog = {type: 'error', message: 'There was an error creating the folder.'};
							};
							$http({method: 'GET', url: scope.path() + '?__p=mk&_d=' + scope.dirPath() + '&_n=' + scope.dialog.file}).
								success(function(data) {
									if (data.status) {
										scope.dialog = false;
										scope.loadFiles();
									} else {
										error();
									}
								}).
								error(error);	
						}
					};					
				});
			});
		};
	})
	.directive('ngDelete', function($http, $location) {
		return function(scope, element) {
			element.bind('click', function(event) {
				scope.$apply(function() {
					scope.dialog = {
						type: 'confirmDelete',
						file: scope.file,
						no: function() {
							scope.dialog = false;
						},
						yes: function() {
							var error = function() {
								scope.dialog = {type: 'error', message: 'There was an error deleting the ' + (scope.type == 'dir' ? 'folder' : 'file') + '.'};
							};
							$http({method: 'GET', url: scope.path() + '?__p=rm&_d=' + scope.dirPath()}).
								success(function(data) {
									if (data.status) {
										scope.dialog = false;
										$location.path(scope.file.path);
									} else {
										error();
									}
								}).
								error(error);	
						}
					};					
				});
			});
		};
	})
	.directive('ngSave', function($http, $location) {
		return function(scope, element) {
			element.bind('click', function(event) {
				scope.saveFile();
			});
		};
	})
	.directive('ngFullscreenEditor', function($http, $location) {
		return function(scope, element) {
			element.bind('click', function(event) {
				scope.$apply(function() {
					scope.fullscreenEdit = true;
				});
			});
		};
	})
	.directive('ngModal', function() {
		return function(scope, element) {
			element.bind('click', function(event) {
				if (event.target == element[0]) {
					scope.$apply(function() {
						scope.dialog = false;					
					});
				}
			});
		};
	})
	.directive('ngBody', function() {
		return function(scope, element) {
			element.bind('keydown keypress', function(event) {
				if (event.which == 27) {
					scope.$apply(function() {
						scope.dialog = false;
						scope.fullscreenEdit = false;			
					});
				}
			});
		};
	})
	.directive('ngAutofocus', function() {
		return function(scope, element, attrs) {
			if (attrs.ngAutofocus) {
				scope.$watch(attrs.ngAutofocus, function(value) {
					setTimeout(function() {
						element[0].focus();
					},10);
				});
			}
		};
	})
	.directive('ngFileName', function($http) {
		return function(scope, element) {
			var save = function() {
				var error = function() {
					scope.dialog = {type: 'error', message: 'There was an error renaming the file.'};
				};
				$http({method: 'GET', url: scope.path() + '?__p=rn&_d=' + scope.dirPath() + '&_n=' + scope.file.name}).
					success(function(data) {
					console.log(data);
						if (data.status) {

						} else {
							error();
						}
					}).
					error(error);	
			};
			element
				.bind('change', function(event) {
					clearTimeout(scope.nameChange);
					scope.nameChange = setTimeout(save, 1000);
				})
				.bind('blur', function(event) {
					save();
				});
		};
	});

Object.size = function(obj) {
	var size = 0, key;
	for (key in obj) {
		if (obj.hasOwnProperty(key)) size++;
	}
	return size;
};

