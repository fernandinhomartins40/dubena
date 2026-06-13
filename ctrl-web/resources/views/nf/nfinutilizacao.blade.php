<?php
    ob_start();

    // Criando o objeto
    $z = new ZipArchive();

    $msgretorno;
    $msgsistema;
    $empresainutilizacao_id;
    $xmlinutilizacaoeretorno;
    $namezip = 'inutilizacao' . $empresainutilizacao_id;
    $namefile = 'inutilizacao' . $empresainutilizacao_id;

    // Criando o pacote chamado "teste.zip"
    $criou = $z->open($namezip . '.zip', ZipArchive::CREATE);
    if ($criou === true) {

        // Criando um diretorio chamado "xmls" dentro do pacote
        //$z->addEmptyDir('xmls');

        //foreach ($nfs as $nf) {
            // Criando um XML dentro do diretorio "xmls" a partir do valor de uma string
            $z->addFromString($namefile . '.xml', $xmlinutilizacaoeretorno);
        //}

        // Criando um TXT dentro do diretorio "teste" a partir do valor de uma string
        //$z->addFromString('teste/texto.txt', 'Conteúdo do arquivo de Texto');

        // Criando outro TXT dentro do diretorio "teste"
        //$z->addFromString('teste/outro.txt', 'Outro arquivo');

        // Copiando um arquivo do HD para o diretorio "teste" do pacote
        //$z->addFile('teste.php', 'teste/teste.php');

        // Apagando o segundo TXT
        //$z->deleteName('teste/outro.txt');

        // Salvando o arquivo
        $z->close();


        //SETANDO OS HEADERS NECESSARIOS
        header("Content-length: " . filesize( $namezip . ".zip" ) );
        header("Content-type: application/octet-stream"); 
        header("Content-disposition: attachment; filename=".$namezip.".zip");

        //ABRINDO O ARQUIVO 
        readfile($namezip . ".zip");
    } else {
        echo 'Problemas ao realizar inutlização: ' . $criou;
    }
?>