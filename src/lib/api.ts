import apiFetch from '@wordpress/api-fetch';

declare const cosellHive: { nonce: string; restUrl: string };

apiFetch.use( apiFetch.createNonceMiddleware( cosellHive.nonce ) );

export async function get<T>( path: string ): Promise< T > {
	return apiFetch< T >( { path } );
}

export async function post<T>( path: string, data: unknown ): Promise< T > {
	return apiFetch< T >( { path, method: 'POST', data } );
}
