var $			  = require( 'gulp-load-plugins' )();
var autoprefixer  = require( 'gulp-autoprefixer' );
var config		  = require( '../util/loadConfig' ).sass;
var gulp		  = require( 'gulp' );
var gulpif		  = require( 'gulp-if' );
var sass		  = require( 'gulp-sass' );
var concat		  = require( 'gulp-concat' );
var notify		  = require( 'gulp-notify' );
var fs			  = require( 'fs' );
var pkg		     = JSON.parse( fs.readFileSync( './package.json' ) );

// This needs defined here too to prevent errors on default task
isRelease = false;

gulp.task( 'sass:front', function() {

	return gulp.src( config.front.src )
		.pipe( $.sourcemaps.init() )
		.pipe( 
			$.sass( {
				includePaths: config.front.vendor
			} )
			.on( 'error', notify.onError( {
				title: pkg.name,
				message: "<%= error.message %>",
			} )
		 ) )
		.pipe( concat( config.front.filename ) )
		.pipe( autoprefixer( config.compatibility ) )
		.pipe( $.cssnano() )
		.pipe( gulpif( ! isRelease, $.sourcemaps.write( '.' ) ) )
		.pipe( gulp.dest( config.front.root ) )
		.pipe( notify( {
			title: pkg.name,
			message: 'SASS Complete',
			onLast: true
		} ) );

} );

gulp.task( 'sass:admin', function() {

	return gulp.src( config.admin.src )
		.pipe( $.sourcemaps.init() )
		.pipe( 
			$.sass( {
				includePaths: config.admin.vendor
			} )
			.on( 'error', notify.onError( {
				title: pkg.name,
				message: "<%= error.message %>",
			} )
		 ) )
		.pipe( concat( config.admin.filename ) )
		.pipe( autoprefixer( config.compatibility ) )
		.pipe( $.cssnano() )
		.pipe( gulpif( ! isRelease, $.sourcemaps.write( '.' ) ) )
		.pipe( gulp.dest( config.admin.root ) )
		.pipe( notify( {
			title: pkg.name,
			message: 'Admin SASS Complete',
			onLast: true
		} ) );

} );

gulp.task( 'sass:form', function() {

	return gulp.src( config.form.src )
		.pipe( $.sourcemaps.init() )
		.pipe( 
			$.sass( {
				includePaths: config.form.vendor
			} )
			.on( 'error', notify.onError( {
				title: pkg.name,
				message: "<%= error.message %>",
			} )
		 ) )
		.pipe( concat( config.form.filename ) )
		.pipe( autoprefixer( config.compatibility ) )
		.pipe( $.cssnano() )
		.pipe( gulpif( ! isRelease, $.sourcemaps.write( '.' ) ) )
		.pipe( gulp.dest( config.form.root ) )
		.pipe( notify( {
			title: pkg.name,
			message: 'Form SASS Complete',
			onLast: true
		} ) );

} );

gulp.task( 'sass:licensing', function() {

	return gulp.src( config.licensing.src )
		.pipe( $.sourcemaps.init() )
		.pipe( 
			$.sass( {
				includePaths: config.licensing.vendor
			} )
			.on( 'error', notify.onError( {
				title: pkg.name,
				message: "<%= error.message %>",
			} )
		 ) )
		.pipe( concat( config.licensing.filename ) )
		.pipe( autoprefixer( config.compatibility ) )
		.pipe( $.cssnano() )
		.pipe( gulpif( ! isRelease, $.sourcemaps.write( '.' ) ) )
		.pipe( gulp.dest( config.licensing.root ) )
		.pipe( notify( {
			title: pkg.name,
			message: 'Licensing SASS Complete',
			onLast: true
		} ) );

} );

gulp.task( 'sass', ['sass:front', 'sass:admin', 'sass:form', 'sass:licensing'], function( done ) {
	done();
} );