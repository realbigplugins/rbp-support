var config	  	= require( '../util/loadConfig' ).watch;
var gulp		= require( 'gulp' );

// Watch files for changes, recompile/rebuild
gulp.task( 'watch', function() {
	gulp.watch( config.javascript.front, ['uglify:front'] );
	gulp.watch( config.javascript.admin, ['uglify:admin'] );
	gulp.watch( config.javascript.form, ['uglify:form'] );
	gulp.watch( config.javascript.licensing, ['uglify:licensing'] );
	gulp.watch( config.javascript.tinymce, ['uglify:tinymce'] );
	
	gulp.watch( config.sass.front, ['sass:front'] );
	gulp.watch( config.sass.admin, ['sass:admin'] );
	gulp.watch( config.sass.form, ['sass:form'] );
	gulp.watch( config.sass.licensing, ['sass:licensing'] );
} );