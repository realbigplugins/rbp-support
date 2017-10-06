var $			 	= require( 'gulp-load-plugins' )();
var config			= require( '../util/loadConfig' ).release;
var gulp		  	= require( 'gulp' );
var notify			= require( 'gulp-notify' );
var fs				= require( 'fs' );
var pkg		  		= JSON.parse( fs.readFileSync( './package.json' ) );
var packageName		= pkg.name.toLowerCase().replace( /_/g, '-' ).replace( /\s/g, '-' ).trim();

require( 'gulp-grunt' )( gulp, {
	prefix: 'release:grunt-',
} ); // add all the gruntfile tasks to gulp

gulp.task( 'release:localization', function( done ) {
	
	isDebug = false;

	return gulp.src( './**/*.php' )
		.pipe( $.sort() )
		.pipe( $.wpPot( {
			domain: packageName,
			destFile: packageName + '.pot',
			package: pkg.name,
		} ) )
		.pipe( gulp.dest( config.languagesDir ) );

} );

gulp.task( 'release:copy', function( done ) {
	
	return gulp.src( config.files )
		.pipe( gulp.dest( './' + packageName ) );
	
} );

gulp.task( 'release:rename', function( done ) {
	
	var version = pkg.version;
	
	fs.renameSync( './' + packageName + '.zip', './' + packageName + '-' + version + '.zip' );
	isDebug = false;
	
	return done();
	
} );

gulp.task( 'release:cleanup', function( done ) {
	
	return gulp.src( './' + packageName, { read: false } )
		.pipe( $.clean() )
		.pipe( notify( {
			title: pkg.name,
			message: 'Release Built'
		} ) );
	
} );

gulp.task( 'release', function( done ) {
	$.sequence( 'release:localization', 'sass', 'uglify', 'release:copy', 'release:grunt-compress', 'release:rename', 'release:cleanup', done );
} );