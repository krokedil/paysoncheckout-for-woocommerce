/* globals require */
var gulp = require('gulp');
var sort = require('gulp-sort');
var wpPot = require('gulp-wp-pot');

var translateFiles = '**/*.php';

gulp.task('makePOT', function () {
	return gulp.src('**/*.php')
		.pipe(sort())
		.pipe(wpPot({
			domain: 'woocommerce-gateway-paysoncheckout',
			destFile: 'languages/paysoncheckout-for-woocommerce.pot',
			package: 'paysoncheckout-for-woocommerce',
			bugReport: 'http://krokedil.se',
			lastTranslator: 'Krokedil <info@krokedil.se>',
			team: 'Krokedil <info@krokedil.se>'
		}))
		.pipe(gulp.dest('languages/paysoncheckout-for-woocommerce.pot'));
});

gulp.task('watch', function() {
    gulp.watch(translateFiles, ['makePOT']);
});