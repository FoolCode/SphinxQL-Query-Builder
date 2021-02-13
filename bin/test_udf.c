//------------- see sphinxudf.h -------------//
#define SPH_UDF_VERSION 8
typedef struct st_sphinx_udf_args SPH_UDF_ARGS;
typedef struct st_sphinx_udf_init SPH_UDF_INIT;
//-------------------------------------------//

int test_udf_ver()
{
    return SPH_UDF_VERSION;
}

int my_udf(SPH_UDF_INIT* init, SPH_UDF_ARGS* args, char* error_flag)
{
    return 42;
}
