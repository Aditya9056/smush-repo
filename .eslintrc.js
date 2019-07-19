module.exports = {
    'env': {
        'browser': true,
        'es6': true,
    },
    'extends': 'plugin:@wordpress/eslint-plugin/recommended',
    'globals': {
        'Atomics': 'readonly',
        'SharedArrayBuffer': 'readonly',
    },
    'parserOptions': {
        'ecmaVersion': 2018,
        'sourceType': 'module',
    },
    'rules': {
        'no-invalid-this': 0,
    },
};